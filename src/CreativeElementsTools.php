<?php

/**
 * Copyright (c) 2025 PrestaShop SA
 *
 * All Rights Reserved.
 *
 * This module is proprietary software owned by PrestaShop SA. All intellectual property rights, including copyrights, trademarks, and trade secrets, are reserved by PrestaShop SA.
 *
 * The PS MCP Tools module was developed by PrestaShop, which holds all associated intellectual property rights. The license granted to the user does not entail any transfer of rights. The user shall refrain from any act that may infringe upon PrestaShop's rights and undertakes to strictly comply with the limitations of the license set out below. PrestaShop grants the user a personal, non-exclusive, non-transferable, and non-sublicensable license to use the MCP Tools module, worldwide and for the entire duration of use of the module. This license is strictly limited to installing the module and using it solely for the operation of the user's PrestaShop store.
 */

namespace PrestaShop\Module\PsMcpTools;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Tools to read and write Creative Elements (page builder) designs.
 *
 * Creative Elements does not expose a webservice resource, so these tools talk
 * to its storage directly (verified against creativeelements 2.14.0 source):
 * - designs live in `ps_ce_meta` (id, name, value), keyed by a composite
 *   "uid" = {objectId}{type:2}{langId:2}{shopId:2} (see CE\UId).
 * - templates are `ps_ce_template` rows (CETemplate ObjectModel); their
 *   actual builder JSON still lives in `ps_ce_meta` under that template's uid
 *   (type=1), same as any other document.
 * - revisions are `ps_ce_revision` rows (CERevision ObjectModel): parent
 *   (the uid, as string), id_employee, title, type, content, active, date_upd.
 *
 * Writes prefer Creative Elements' own API
 * (CE\Plugin::instance()->documents->get($uid)->save(...)) when it is usable.
 * In practice that API silently no-ops (`save()` returns `false`) unless the
 * current PrestaShop context has an employee with edit rights on the
 * relevant admin controller (CE\User::isCurrentUserCanEdit()) - which an
 * MCP/headless call usually does not have - so every write here checks the
 * real return value and falls back to a direct, schema-aware SQL write
 * (with its own revision backup and CSS cache invalidation) whenever the
 * native path isn't usable or didn't actually apply.
 */
class CreativeElementsTools
{
    // Creative Elements object types (CE\UId constants)
    private const TYPE_REVISION = 0;
    private const TYPE_TEMPLATE = 1;
    private const TYPE_CONTENT = 2;
    private const TYPE_PRODUCT = 3;
    private const TYPE_CATEGORY = 4;
    private const TYPE_MANUFACTURER = 5;
    private const TYPE_SUPPLIER = 6;
    private const TYPE_CMS = 7;
    private const TYPE_CMS_CATEGORY = 8;
    private const TYPE_THEME = 17;

    private const TYPE_DESCRIPTION = 'Creative Elements object type: 0=revision, 1=template, 2=content (hooks), 3=product, 4=category, 5=manufacturer, 6=supplier, 7=CMS page, 8=CMS category, 17=theme.';

    // ps_ce_meta.name keys used by Creative Elements
    private const META_DATA = '_elementor_data';
    private const META_SETTINGS = '_elementor_page_settings';
    private const META_EDIT_MODE = '_elementor_edit_mode';
    private const META_VERSION = '_elementor_version';
    private const META_TEMPLATE = '_wp_page_template';
    private const META_CSS = '_elementor_css';
    private const META_DATE_UPD = '_ce_date_upd';

    /**
     * Configuration key restricting which pages this module is allowed to write to.
     * Comma separated list of "type:objectId" (e.g. "7:105,7:106"), or bare
     * objectId values. Leave empty to allow writes to any page.
     */
    private const CONFIG_ALLOWED_TARGETS = 'PS_MCP_CE_ALLOWED_TARGETS';

    /** @var bool|null Cached result of bootstrapping CE\Plugin for this request */
    private static ?bool $nativeApiReady = null;

    /** @var array<int, string>|null Cached column list of ps_ce_revision */
    private static ?array $revisionColumns = null;

    /** @var bool Whether error suppression is active (mirrors AbstractWebservice) */
    private static bool $errorSuppressionActive = false;

    public function __construct()
    {
        // Some Creative Elements code paths touch globals (e.g. $GLOBALS['employee'])
        // that may be unset outside a normal front/admin request; a stray PHP
        // warning here must not corrupt the MCP JSON-RPC response stream.
        if (!self::$errorSuppressionActive) {
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
            set_error_handler(function () {
                return true;
            }, E_ALL);
            self::$errorSuppressionActive = true;
        }
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'ce_get_page',
        description: 'Retrieve the Creative Elements design (Elementor-style JSON) stored for a CMS page, product, category or template.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'type' => ['type' => 'integer', 'description' => self::TYPE_DESCRIPTION],
            'objectId' => ['type' => 'integer', 'description' => 'ID of the CMS page / product / category / template.'],
            'langId' => ['type' => 'integer', 'description' => 'Language ID (ignored for templates, which are not translated).'],
            'shopId' => ['type' => 'integer', 'description' => 'Shop ID (ignored for templates).'],
        ],
        required: ['type', 'objectId', 'langId', 'shopId']
    )]
    public function ceGetPage(int $type, int $objectId, int $langId, int $shopId): array
    {
        $uid = $this->buildUid($type, $objectId, $langId, $shopId);
        $rows = $this->getMetaRows($uid);

        if (empty($rows)) {
            throw new \PrestaShopException(sprintf(
                'No Creative Elements design found for type=%d, objectId=%d, langId=%d, shopId=%d (uid=%d).',
                $type,
                $objectId,
                $langId,
                $shopId,
                $uid
            ));
        }

        $result = [
            'uid' => $uid,
            'elements' => null,
            'settings' => null,
            'editMode' => null,
            'version' => null,
            'pageTemplate' => null,
        ];

        foreach ($rows as $row) {
            switch ($row['name']) {
                case self::META_DATA:
                    $result['elements'] = $this->decodeJsonOrRaw($row['value']);
                    break;
                case self::META_SETTINGS:
                    $result['settings'] = $this->decodeJsonOrRaw($row['value']);
                    break;
                case self::META_EDIT_MODE:
                    $result['editMode'] = $row['value'];
                    break;
                case self::META_VERSION:
                    $result['version'] = $row['value'];
                    break;
                case self::META_TEMPLATE:
                    $result['pageTemplate'] = $row['value'];
                    break;
            }
        }

        return $result;
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'ce_save_page',
        description: 'Save a full Creative Elements design for a CMS page, product or category. The previous design is automatically backed up as a revision before saving, so it can be restored with ce_restore_revision.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'type' => ['type' => 'integer', 'description' => self::TYPE_DESCRIPTION],
            'objectId' => ['type' => 'integer', 'description' => 'ID of the CMS page / product / category to save the design for.'],
            'langId' => ['type' => 'integer', 'description' => 'Language ID this design applies to.'],
            'shopId' => ['type' => 'integer', 'description' => 'Shop ID this design applies to.'],
            'elements' => ['type' => 'array', 'description' => 'Full Creative Elements/Elementor elements tree (the decoded _elementor_data JSON).'],
            'settings' => ['type' => ['object', 'null'], 'description' => 'Optional page settings object (_elementor_page_settings). Omit to leave existing settings untouched.'],
        ],
        required: ['type', 'objectId', 'langId', 'shopId', 'elements']
    )]
    public function ceSavePage(int $type, int $objectId, int $langId, int $shopId, array $elements, ?array $settings = null): array
    {
        $this->assertWritable($type, $objectId);
        $uid = $this->buildUid($type, $objectId, $langId, $shopId);

        $previousData = $this->getMetaValue($uid, self::META_DATA);
        $revisionCreated = $previousData !== null && $this->createRevision($uid, $previousData);

        $usedNativeApi = false;
        if ($this->bootstrapNativeApi()) {
            try {
                $document = \CE\Plugin::$instance->documents->get($uid);
                if ($document) {
                    $payload = ['elements' => $elements];
                    if ($settings !== null) {
                        $payload['settings'] = $settings;
                    }
                    // save() returns exactly `true` on success and `false` if the
                    // current context has no employee with edit rights on this
                    // page - which silently does nothing, so only `=== true`
                    // counts as a real save.
                    $usedNativeApi = true === $document->save($payload);
                }
            } catch (\Throwable $e) {
                $this->logChange('ce_native_save_failed', $uid, ['error' => $e->getMessage()]);
            }
        }

        if (!$usedNativeApi) {
            $this->writeDesignDirectly($uid, $elements, $settings);
        }

        $this->logChange('ce_save_page', $uid, [
            'type' => $type,
            'objectId' => $objectId,
            'langId' => $langId,
            'shopId' => $shopId,
            'usedNativeApi' => $usedNativeApi,
            'revisionCreated' => $revisionCreated,
        ]);

        return [
            'uid' => $uid,
            'saved' => true,
            'usedNativeApi' => $usedNativeApi,
            'revisionCreated' => $revisionCreated,
        ];
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'ce_list_templates',
        description: 'List Creative Elements templates stored in the local template library (ps_ce_template).'
    )]
    public function ceListTemplates(): array
    {
        $sql = new \DbQuery();
        $sql->select('*')->from('ce_template')->orderBy('id_ce_template ASC');
        $rows = \Db::getInstance()->executeS($sql);

        return ['templates' => is_array($rows) ? $rows : []];
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'ce_import_template',
        description: 'Import a Creative Elements/Elementor template export JSON (the "version": "0.4" format, with title/type/content/page_settings) into the local template library.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'template' => [
                'type' => 'object',
                'description' => 'Decoded template JSON, e.g. {"version":"0.4","title":"...","type":"page","content":[...],"page_settings":{...}}.',
                'properties' => [
                    'version' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'type' => ['type' => 'string'],
                    'content' => ['type' => 'array'],
                    'page_settings' => ['type' => ['object', 'null']],
                ],
                'required' => ['title', 'content'],
            ],
        ],
        required: ['template']
    )]
    public function ceImportTemplate(array $template): array
    {
        if (!isset($template['content']) || !is_array($template['content'])) {
            throw new \InvalidArgumentException('Template JSON must contain a "content" array (the elements tree).');
        }

        $title = (string) ($template['title'] ?? 'Imported template');
        $templateType = (string) ($template['type'] ?? 'page');
        $pageSettings = is_array($template['page_settings'] ?? null) ? $template['page_settings'] : [];

        $usedNativeApi = false;
        $uid = null;

        if ($this->bootstrapNativeApi()) {
            try {
                $source = \CE\Plugin::$instance->templates_manager->getSource('local');
                if ($source) {
                    // CE\TemplateLibraryXSourceLocal::saveItem() - real API used by
                    // the "Save as template" feature in the builder itself.
                    $result = $source->saveItem([
                        'title' => $title,
                        'type' => $templateType,
                        'content' => $template['content'],
                        'page_settings' => $pageSettings,
                    ]);
                    if (!($result instanceof \CE\WPError) && $result) {
                        // saveItem() returns the document's main id, which for CE
                        // documents is the composite uid string, not the raw
                        // ps_ce_template.id_ce_template.
                        $uid = (int) $result;
                        $usedNativeApi = true;
                    }
                }
            } catch (\Throwable $e) {
                $this->logChange('ce_native_import_template_failed', 0, ['error' => $e->getMessage()]);
            }
        }

        if (!$usedNativeApi) {
            $employeeId = $this->getCurrentEmployeeId();

            $tpl = new \CETemplate();
            $tpl->id_employee = $employeeId;
            $tpl->title = $title;
            $tpl->type = $templateType;
            $tpl->position = 0;
            $tpl->active = 1;
            $tpl->date_add = date('Y-m-d H:i:s');
            $tpl->date_upd = date('Y-m-d H:i:s');

            if (!$tpl->add()) {
                throw new \PrestaShopException('Failed to insert into ce_template: ' . \Db::getInstance()->getMsgError());
            }

            $uid = $this->buildUid(self::TYPE_TEMPLATE, (int) $tpl->id, 0, 0);
            $this->writeDesignDirectly($uid, $template['content'], $pageSettings ?: null);
            if (isset($template['version'])) {
                $this->setMetaValue($uid, self::META_VERSION, (string) $template['version']);
            }
        }

        $this->logChange('ce_import_template', $uid ?? 0, ['title' => $title, 'usedNativeApi' => $usedNativeApi]);

        return ['imported' => true, 'usedNativeApi' => $usedNativeApi, 'uid' => $uid];
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'ce_clear_cache',
        description: 'Force Creative Elements to regenerate its CSS cache. Pass a uid to clear a single page, or omit it to also flush the global cache.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'uid' => ['type' => ['integer', 'null'], 'description' => 'Composite Creative Elements uid (as returned by ce_get_page/ce_save_page) to clear the CSS cache for.'],
        ],
        required: []
    )]
    public function ceClearCache(?int $uid = null): array
    {
        $cleared = [];

        if ($uid !== null) {
            $this->deleteMetaKey($uid, self::META_CSS);
            $cleared[] = (string) $uid;
        }

        if ($this->bootstrapNativeApi() && isset(\CE\Plugin::$instance->files_manager)) {
            try {
                \CE\Plugin::$instance->files_manager->clearCache();
                $cleared[] = 'global';
            } catch (\Throwable $e) {
                $this->logChange('ce_native_clear_cache_failed', $uid ?? 0, ['error' => $e->getMessage()]);
            }
        }

        $this->logChange('ce_clear_cache', $uid ?? 0, ['cleared' => $cleared]);

        return ['cleared' => $cleared];
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'ce_list_revisions',
        description: 'List the saved revisions (history) of a Creative Elements design, most recent first.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'type' => ['type' => 'integer', 'description' => self::TYPE_DESCRIPTION],
            'objectId' => ['type' => 'integer', 'description' => 'ID of the CMS page / product / category.'],
            'langId' => ['type' => 'integer', 'description' => 'Language ID.'],
            'shopId' => ['type' => 'integer', 'description' => 'Shop ID.'],
        ],
        required: ['type', 'objectId', 'langId', 'shopId']
    )]
    public function ceListRevisions(int $type, int $objectId, int $langId, int $shopId): array
    {
        $uid = $this->buildUid($type, $objectId, $langId, $shopId);

        $sql = new \DbQuery();
        $sql->select('*')->from('ce_revision')->where('parent = \'' . pSQL((string) $uid) . '\'')->orderBy('id_ce_revision DESC');

        $rows = \Db::getInstance()->executeS($sql);

        return ['uid' => $uid, 'revisions' => is_array($rows) ? $rows : []];
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'ce_restore_revision',
        description: 'Restore a previous Creative Elements revision, overwriting the current design. The design being replaced is itself backed up as a new revision first, so this action can be undone.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'revisionId' => ['type' => 'integer', 'description' => 'ID of the revision row (ce_revision.id_ce_revision, from ce_list_revisions) to restore.'],
        ],
        required: ['revisionId']
    )]
    public function ceRestoreRevision(int $revisionId): array
    {
        $sql = new \DbQuery();
        $sql->select('*')->from('ce_revision')->where('id_ce_revision = ' . $revisionId);
        $revision = \Db::getInstance()->getRow($sql);

        if (!$revision) {
            throw new \PrestaShopException(sprintf('Revision %d not found.', $revisionId));
        }

        $uid = (int) $revision['parent'];
        $content = (string) $revision['content'];
        $target = $this->parseUid($uid);

        $this->assertWritable($target['type'], $target['objectId']);

        $currentData = $this->getMetaValue($uid, self::META_DATA);
        $backupCreated = $currentData !== null && $this->createRevision($uid, $currentData);

        $decoded = $this->decodeJsonOrRaw($content);
        $usedNativeApi = false;
        if (is_array($decoded) && $this->bootstrapNativeApi()) {
            try {
                $document = \CE\Plugin::$instance->documents->get($uid);
                if ($document) {
                    $usedNativeApi = true === $document->save(['elements' => $decoded]);
                }
            } catch (\Throwable $e) {
                $this->logChange('ce_native_restore_failed', $uid, ['error' => $e->getMessage()]);
            }
        }

        if (!$usedNativeApi) {
            $this->setMetaValue($uid, self::META_DATA, $content);
            $this->setMetaValue($uid, self::META_DATE_UPD, date('Y-m-d H:i:s'));
            $this->deleteMetaKey($uid, self::META_CSS);
        }

        $this->logChange('ce_restore_revision', $uid, ['revisionId' => $revisionId, 'usedNativeApi' => $usedNativeApi]);

        return [
            'uid' => $uid,
            'restored' => true,
            'usedNativeApi' => $usedNativeApi,
            'backupOfCurrentCreated' => $backupCreated,
        ];
    }

    /**
     * Write elements/settings directly into ps_ce_meta, bypassing Creative
     * Elements' own save pipeline. Since that pipeline is what normally
     * regenerates the CSS cache, the cached CSS is dropped instead so it is
     * rebuilt on next render.
     */
    private function writeDesignDirectly(int $uid, array $elements, ?array $settings): void
    {
        $elementsJson = json_encode($elements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($elementsJson === false) {
            throw new \InvalidArgumentException('Unable to encode elements to JSON: ' . json_last_error_msg());
        }
        $this->setMetaValue($uid, self::META_DATA, $elementsJson);

        if ($settings !== null) {
            $settingsJson = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($settingsJson !== false) {
                $this->setMetaValue($uid, self::META_SETTINGS, $settingsJson);
            }
        }

        if ($this->getMetaValue($uid, self::META_EDIT_MODE) === null) {
            $this->setMetaValue($uid, self::META_EDIT_MODE, 'builder');
        }

        $this->setMetaValue($uid, self::META_DATE_UPD, date('Y-m-d H:i:s'));
        $this->deleteMetaKey($uid, self::META_CSS);
    }

    /**
     * Build the composite Creative Elements uid: {objectId}{type:2}{langId:2}{shopId:2}.
     * Mirrors CE\UId: revisions and templates (type <= 1) are not per-language
     * or per-shop, so langId/shopId are forced to 0 for them.
     */
    private function buildUid(int $type, int $objectId, int $langId, int $shopId): int
    {
        if ($objectId <= 0) {
            throw new \InvalidArgumentException('objectId must be a positive integer.');
        }
        if ($type < 0 || $type > 99 || $langId < 0 || $langId > 99 || $shopId < 0 || $shopId > 99) {
            throw new \InvalidArgumentException('type, langId and shopId must each be between 0 and 99.');
        }
        if ($type <= self::TYPE_TEMPLATE) {
            $langId = 0;
            $shopId = 0;
        }

        return (int) sprintf('%d%02d%02d%02d', $objectId, $type, $langId, $shopId);
    }

    /**
     * Reverse a composite uid back into its {type, objectId, langId, shopId} parts.
     *
     * @return array{type: int, objectId: int, langId: int, shopId: int}
     */
    private function parseUid(int $uid): array
    {
        $str = (string) $uid;
        if (strlen($str) < 7) {
            throw new \PrestaShopException(sprintf('Invalid Creative Elements uid: %d.', $uid));
        }

        return [
            'objectId' => (int) substr($str, 0, -6),
            'type' => (int) substr($str, -6, 2),
            'langId' => (int) substr($str, -4, 2),
            'shopId' => (int) substr($str, -2),
        ];
    }

    /**
     * Restrict writes to an allow-list of "type:objectId" targets, configured
     * via the PS_MCP_CE_ALLOWED_TARGETS configuration key. Empty (default)
     * means writes are allowed everywhere.
     */
    private function assertWritable(int $type, int $objectId): void
    {
        $allowList = trim((string) \Configuration::get(self::CONFIG_ALLOWED_TARGETS));
        if ($allowList === '') {
            return;
        }

        foreach (explode(',', $allowList) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if (strpos($entry, ':') !== false) {
                [$allowedType, $allowedId] = array_map('trim', explode(':', $entry, 2));
                if ((int) $allowedType === $type && (int) $allowedId === $objectId) {
                    return;
                }
            } elseif ((int) $entry === $objectId) {
                return;
            }
        }

        throw new \PrestaShopException(sprintf(
            'Writing to Creative Elements type=%d objectId=%d is not allowed. Add "%d:%d" to the %s configuration to enable it.',
            $type,
            $objectId,
            $type,
            $objectId,
            self::CONFIG_ALLOWED_TARGETS
        ));
    }

    /**
     * Load the creativeelements module (if needed) and boot its CE\Plugin
     * singleton. Memoized for the request: if it fails once (module
     * disabled, or an exception during Plugin::instance()'s component
     * initialization - e.g. because this call has no full front/admin
     * request context) every tool falls back to the direct SQL path instead
     * of retrying on every call.
     */
    private function bootstrapNativeApi(): bool
    {
        if (self::$nativeApiReady !== null) {
            return self::$nativeApiReady;
        }

        try {
            if (!class_exists('CE\\Plugin')) {
                if (!\Module::isEnabled('creativeelements')) {
                    return self::$nativeApiReady = false;
                }
                \Module::getInstanceByName('creativeelements');
            }
            if (!class_exists('CE\\Plugin')) {
                return self::$nativeApiReady = false;
            }

            \CE\Plugin::instance();

            return self::$nativeApiReady = (\CE\Plugin::$instance !== null && isset(\CE\Plugin::$instance->documents));
        } catch (\Throwable $e) {
            $this->logChange('ce_native_bootstrap_failed', 0, ['error' => $e->getMessage()]);

            return self::$nativeApiReady = false;
        }
    }

    /**
     * @return array<int, array{id_ce_meta: string, id: string, name: string, value: string}>
     */
    private function getMetaRows(int $uid): array
    {
        $sql = new \DbQuery();
        $sql->select('id_ce_meta, id, name, value')->from('ce_meta')->where('id = ' . $uid);
        $rows = \Db::getInstance()->executeS($sql);

        return is_array($rows) ? $rows : [];
    }

    private function getMetaValue(int $uid, string $name): ?string
    {
        $sql = new \DbQuery();
        $sql->select('value')->from('ce_meta')->where('id = ' . $uid)->where('name = \'' . pSQL($name) . '\'');
        $value = \Db::getInstance()->getValue($sql);

        return $value === false ? null : (string) $value;
    }

    private function setMetaValue(int $uid, string $name, string $value): void
    {
        $db = \Db::getInstance();
        $existingSql = new \DbQuery();
        $existingSql->select('id_ce_meta')->from('ce_meta')->where('id = ' . $uid)->where('name = \'' . pSQL($name) . '\'');
        $existingId = $db->getValue($existingSql);

        if ($existingId) {
            $db->update('ce_meta', ['value' => $value], 'id_ce_meta = ' . (int) $existingId);
        } else {
            $db->insert('ce_meta', ['id' => $uid, 'name' => $name, 'value' => $value]);
        }
    }

    private function deleteMetaKey(int $uid, string $name): void
    {
        \Db::getInstance()->delete('ce_meta', 'id = ' . $uid . ' AND name = \'' . pSQL($name) . '\'');
    }

    /**
     * Back up the previous design as a ce_revision row. Never throws: this
     * must not block a save. Tries the CERevision ObjectModel first (clean,
     * validated); if that fails - e.g. the known case where the `type`
     * column is missing after a failed 2.5.2 upgrade - falls back to a raw
     * insert that only includes columns that actually exist.
     */
    private function createRevision(int $uid, string $previousDataJson): bool
    {
        $employeeId = $this->getCurrentEmployeeId();
        $title = 'MCP backup ' . date('Y-m-d H:i:s');

        try {
            $revision = new \CERevision();
            $revision->parent = (string) $uid;
            $revision->id_employee = $employeeId;
            $revision->title = $title;
            $revision->type = 'mcp_backup';
            $revision->content = $previousDataJson;
            $revision->active = 1;
            $revision->date_upd = date('Y-m-d H:i:s');

            if ($revision->add()) {
                return true;
            }
        } catch (\Throwable $e) {
            // Fall through to the column-tolerant raw insert below.
        }

        try {
            $columns = $this->getRevisionColumns();
            $data = [
                'parent' => (string) $uid,
                'id_employee' => $employeeId,
                'title' => $title,
                'content' => $previousDataJson,
                'active' => 1,
                'date_upd' => date('Y-m-d H:i:s'),
            ];
            if (in_array('type', $columns, true)) {
                $data['type'] = 'mcp_backup';
            }

            return (bool) \Db::getInstance()->insert('ce_revision', $data);
        } catch (\Throwable $e) {
            $this->logChange('ce_revision_backup_failed', $uid, ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    private function getRevisionColumns(): array
    {
        if (self::$revisionColumns !== null) {
            return self::$revisionColumns;
        }

        $columns = \Db::getInstance()->executeS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'ce_revision`');
        self::$revisionColumns = is_array($columns) ? array_column($columns, 'Field') : [];

        return self::$revisionColumns;
    }

    /**
     * @return mixed
     */
    private function decodeJsonOrRaw(string $value)
    {
        $decoded = json_decode($value, true);

        return $decoded !== null || $value === 'null' ? $decoded : $value;
    }

    private function getCurrentEmployeeId(): int
    {
        $context = \Context::getContext();
        if ($context !== null && $context->employee !== null && $context->employee->id) {
            return (int) $context->employee->id;
        }

        return 0;
    }

    private function logChange(string $action, int $uid, array $context = []): void
    {
        $employeeId = $this->getCurrentEmployeeId();

        $message = sprintf(
            '[PS MCP Tools][CreativeElements] %s uid=%d employee=%d context=%s',
            $action,
            $uid,
            $employeeId,
            json_encode($context, JSON_UNESCAPED_UNICODE) ?: '{}'
        );

        \PrestaShopLogger::addLog($message, 1, null, 'CreativeElements', $uid, true);
    }
}
