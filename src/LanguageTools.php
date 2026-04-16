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

class LanguageTools
{
    /**
     * @return array<int, array<string, mixed>>
     */
    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_languages',
        description: 'Retrieve a list of languages used by the shop.',
    )]
    public function getLanguages(): array
    {
        $languages = \Language::getLanguages(true);

        // Filter to ensure we only return arrays
        return array_values(array_filter($languages, 'is_array'));
    }
}
