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
 * to its storage directly: designs live in `ps_ce_meta`, keyed by a composite
 * "uid" ({objectId}{type:2}{langId:2}{shopId:2}), templates live in
 * `ps_ce_template` (with their content also in `ps_ce_meta`), and revisions
 * live in `ps_ce_revision`. Writes prefer Creative Elements' own API
 * (CE\Plugin::$instance->documents->get($uid)->save(...)) when available, so
 * that revisions, CSS cache and plain-text indexes are handled the same way
 * the page builder itself would; a direct-SQL fallback is used otherwise.
 */
class CreativeElementsTools
{
    // Creative Elements object types (see CE\UId)
    private const TYPE_REVISION = 0;
    private const TYPE_TEMPLATE = 1;
    private const TYPE_CONTENT = 2;
    private const TYPE_PRODUCT = 3;
    private const TYPE_CATEGORY = 4;
    private const TYPE_CMS = 7;
    private const TYPE_CMS_CATEGORY = 8;
    private const TYPE_THEME = 17;

    private const TYPE_DESCRIPTION = 'Creative Elements object type: 0=revision, 1=template, 2=content, 3=product, 4=category, 7=CMS page, 8=CMS category, 17=theme.';

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

    /**
     * Candidate FQCNs for Creative Elements' local template library source,
     * tried in order since the exact namespace is not part of any public API.
     */
    private const TEMPLATE_LIBRARY_CLASSES = [
        '\\CE\\TemplateLibrary\\Sources\\Local',
        '\\CE\\TemplateLibrary\\Sources\\SourceLocal',
        '\\CE\\Includes\\TemplateLibrary\\Sources\\Local',
        '\\CE\\Includes\\TemplateLibrary\\Sources\\SourceLocal',
    ];

    /** @var array<int, string>|null Cached column list of ps_ce_revision */
    private static ?array $revisionColumns = null;

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'ce_get_page',
        description: 'Retrieve the Creative Elements design (Elementor-style JSON) stored for a CMS page, product, category or template.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'type' => ['type' => 'integer', 'description' => self::TYPE_DESCRIPTION],
            'objectId' => ['type' => 'integer', 'description' => 'ID of the CMS page / product / category / template.'],
            'langId' => ['type' => 'integer', 'description' => 'Language ID (use 0 for templates, which are not translated).'],
            'shopId' => ['type' => 'integer', 'description' => 'Shop ID (use 0 for templates).'],
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
        if ($this->nativeApiAvailable()) {
            $document = \CE\Plugin::$instance->documents->get($uid);
            if ($document !== null) {
                $payload = ['elements' => $elements];
                if ($settings !== null) {
                    $payload['settings'] = $settings;
                }
                $document->save($payload);
                $usedNativeApi = true;
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
        $sql->select('*')->from('ce_template')->orderBy('id ASC');
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

        foreach (self::TEMPLATE_LIBRARY_CLASSES as $class) {
            if (class_exists($class) && method_exists($class, 'importTemplate')) {
                $result = $class::importTemplate($template);

                $this->logChange('ce_import_template', 0, ['title' => $title, 'usedNativeApi' => true, 'class' => $class]);

                return ['imported' => true, 'usedNativeApi' => true, 'result' => $result];
            }
        }

        // Fallback: create the template row and its meta entries directly.
        $db = \Db::getInstance();
        $inserted = $db->insert('ce_template', [
            'title' => $title,
            'type' => (string) ($template['type'] ?? 'page'),
            'date_add' => date('Y-m-d H:i:s'),
            'date_upd' => date('Y-m-d H:i:s'),
        ]);
        if (!$inserted) {
            throw new \PrestaShopException('Failed to insert into ce_template: ' . $db->getMsgError());
        }
        $templateId = (int) $db->Insert_ID();
        $uid = $this->buildUid(self::TYPE_TEMPLATE, $templateId, 0, 0);

        $this->writeDesignDirectly($uid, $template['content'], is_array($template['page_settings'] ?? null) ? $template['page_settings'] : null);
        if (isset($template['version'])) {
            $this->setMetaValue($uid, self::META_VERSION, (string) $template['version']);
        }

        $this->logChange('ce_import_template', $uid, ['title' => $title, 'usedNativeApi' => false, 'templateId' => $templateId]);

        return ['imported' => true, 'usedNativeApi' => false, 'templateId' => $templateId, 'uid' => $uid];
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

        if ($this->nativeApiAvailable() && isset(\CE\Plugin::$instance->files_manager)) {
            \CE\Plugin::$instance->files_manager->clearCache();
            $cleared[] = 'global';
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
        $columns = $this->getRevisionColumns();
        $uidColumn = $this->resolveRevisionUidColumn($columns);

        $sql = new \DbQuery();
        $sql->select('*')->from('ce_revision')->where($uidColumn . ' = ' . $uid);
        $sql->orderBy(in_array('date_add', $columns, true) ? 'date_add DESC' : 'id DESC');

        $rows = \Db::getInstance()->executeS($sql);

        return ['uid' => $uid, 'revisions' => is_array($rows) ? $rows : []];
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'ce_restore_revision',
        description: 'Restore a previous Creative Elements revision, overwriting the current design. The design being replaced is itself backed up as a new revision first, so this action can be undone.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'revisionId' => ['type' => 'integer', 'description' => 'ID of the revision row (from ce_list_revisions) to restore.'],
        ],
        required: ['revisionId']
    )]
    public function ceRestoreRevision(int $revisionId): array
    {
        $columns = $this->getRevisionColumns();
        $uidColumn = $this->resolveRevisionUidColumn($columns);
        $valueColumn = $this->resolveRevisionValueColumn($columns);

        $sql = new \DbQuery();
        $sql->select('*')->from('ce_revision')->where('id = ' . $revisionId);
        $revision = \Db::getInstance()->getRow($sql);

        if (!$revision) {
            throw new \PrestaShopException(sprintf('Revision %d not found.', $revisionId));
        }

        $uid = (int) $revision[$uidColumn];
        $content = (string) $revision[$valueColumn];
        $target = $this->parseUid($uid);

        $this->assertWritable($target['type'], $target['objectId']);

        $currentData = $this->getMetaValue($uid, self::META_DATA);
        $backupCreated = $currentData !== null && $this->createRevision($uid, $currentData);

        $decoded = $this->decodeJsonOrRaw($content);
        $usedNativeApi = false;
        if (is_array($decoded) && $this->nativeApiAvailable()) {
            $document = \CE\Plugin::$instance->documents->get($uid);
            if ($document !== null) {
                $document->save(['elements' => $decoded]);
                $usedNativeApi = true;
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

        $this->setMetaValue($uid, self::META_DATE_UPD, date('Y-m-d H:i:s'));
        $this->deleteMetaKey($uid, self::META_CSS);
    }

    /**
     * Build the composite Creative Elements uid: {objectId}{type:2}{langId:2}{shopId:2}.
     */
    private function buildUid(int $type, int $objectId, int $langId, int $shopId): int
    {
        if ($objectId <= 0) {
            throw new \InvalidArgumentException('objectId must be a positive integer.');
        }
        if ($type < 0 || $type > 99 || $langId < 0 || $langId > 99 || $shopId < 0 || $shopId > 99) {
            throw new \InvalidArgumentException('type, langId and shopId must each be between 0 and 99.');
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

    private function nativeApiAvailable(): bool
    {
        return class_exists('CE\\Plugin') && isset(\CE\Plugin::$instance) && isset(\CE\Plugin::$instance->documents);
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
     * Back up the previous design as a revision row. Never throws: if the
     * revisions table schema is unexpected (this has happened before, see a
     * missing "type" column after a failed 2.5.2 upgrade) the save must still
     * go through, so failures here are logged instead of blocking the caller.
     */
    private function createRevision(int $uid, string $previousDataJson): bool
    {
        try {
            $columns = $this->getRevisionColumns();
            $uidColumn = $this->resolveRevisionUidColumn($columns);
            $valueColumn = $this->resolveRevisionValueColumn($columns);

            $data = [
                $uidColumn => $uid,
                $valueColumn => $previousDataJson,
            ];
            if (in_array('type', $columns, true)) {
                $data['type'] = self::TYPE_REVISION;
            }
            if (in_array('date_add', $columns, true)) {
                $data['date_add'] = date('Y-m-d H:i:s');
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
     * @param array<int, string> $columns
     */
    private function resolveRevisionUidColumn(array $columns): string
    {
        foreach (['uid', 'id_ce_meta', 'id_post', 'id_page', 'post_id', 'id_content'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        throw new \PrestaShopException('Unable to determine which column in ps_ce_revision references the page uid; please check the table schema manually.');
    }

    /**
     * @param array<int, string> $columns
     */
    private function resolveRevisionValueColumn(array $columns): string
    {
        foreach (['value', 'content', 'data', 'post_content'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        throw new \PrestaShopException('Unable to determine which column in ps_ce_revision stores the design content; please check the table schema manually.');
    }

    /**
     * @return mixed
     */
    private function decodeJsonOrRaw(string $value)
    {
        $decoded = json_decode($value, true);

        return $decoded !== null || $value === 'null' ? $decoded : $value;
    }

    private function logChange(string $action, int $uid, array $context = []): void
    {
        $employeeId = null;
        $context1 = \Context::getContext();
        if ($context1 !== null && $context1->employee !== null && $context1->employee->id) {
            $employeeId = (int) $context1->employee->id;
        }

        $message = sprintf(
            '[PS MCP Tools][CreativeElements] %s uid=%d employee=%s context=%s',
            $action,
            $uid,
            $employeeId !== null ? (string) $employeeId : 'n/a',
            json_encode($context, JSON_UNESCAPED_UNICODE) ?: '{}'
        );

        \PrestaShopLogger::addLog($message, 1, null, 'CreativeElements', $uid, true);
    }
}
