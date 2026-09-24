# PrestaShop MCP Tools

PrestaShop MCP Tools is a PrestaShop module that embeds MCP (Model Context Protocol) tools to manage your PrestaShop store.

This module provides a comprehensive set of tools for interacting with PrestaShop's SOAP webservices, enabling automated management of customers, orders, products, and languages.

It leverages PrestaShop's SOAP webservices API to perform various operations on your store data, making it possible to interact with your PrestaShop installation through MCP-compatible interfaces.

## Requirements

-   **PrestaShop**: 8.2 or higher
-   **PHP**: 8.1 or higher
-   **Dependencies**:
    -   PS MCP Server module (required)
    -   PrestaShop SOAP webservices must be enabled and configured

## Installation

By default, the module is installed automatically when the PS MCP Server module is installed.

But you can also install it manually:

1. Download or clone this module into your PrestaShop `modules` directory
2. Ensure the PS MCP Server module is installed and active
3. Install the module through the PrestaShop back office or via command line
4. Configure PrestaShop webservices if not already done

## Features

The module provides the following MCP tools:

-   **CustomerTools**: Manage customer accounts, retrieve customer information, and perform customer-related operations
-   **OrderTools**: Handle order management, retrieve order details, and process order operations
-   **ProductTools**: Manage products, categories, and product-related data
-   **ProductImageTools**: Manage product images
-   **LanguageTools**: Handle language and localization operations
-   **CreativeElementsTools**: Read and write Creative Elements page-builder designs (CMS pages, products, categories, templates and revisions)

Most tools interact with PrestaShop through the SOAP webservices API, ensuring secure and standardized access to your store data. `CreativeElementsTools` is the exception: Creative Elements does not expose a webservice resource, so it reads/writes its storage directly (`ps_ce_meta`, `ps_ce_template`, `ps_ce_revision`), preferring Creative Elements' own save API when available.

### Creative Elements tools

-   `ce_get_page(type, objectId, langId, shopId)`: retrieve the design (`_elementor_data`/`_elementor_page_settings`) stored for a CMS page, product, category or template.
-   `ce_save_page(type, objectId, langId, shopId, elements, settings?)`: save a full design. Automatically backs up the previous design as a revision first.
-   `ce_list_templates()` / `ce_import_template(template)`: list and import local library templates (same JSON export format used by the page builder).
-   `ce_clear_cache(uid?)`: force regeneration of the CSS cache for a page, or globally.
-   `ce_list_revisions(type, objectId, langId, shopId)` / `ce_restore_revision(revisionId)`: inspect and roll back to a previous revision.

Writes can be restricted to specific pages via the `PS_MCP_CE_ALLOWED_TARGETS` configuration key (comma-separated `type:objectId` pairs, e.g. `7:105`); leave it empty to allow writes anywhere. Every write is logged via `PrestaShopLogger`.

## Usage

This module is designed to work with the PS MCP Server module. Once installed, the tools will be available through the MCP interface, allowing LLM applications to interact with your PrestaShop store.

## Development

### Project Structure

```
ps_mcp_tools/
├── src/
│   ├── Webservice/                     # Base classes for webservice-backed tools
│   ├── CreativeElementsTools.php       # Creative Elements page-builder tools
│   ├── CustomerTools.php               # Customer management tools
│   ├── LanguageTools.php               # Language management tools
│   ├── OrderTools.php                  # Order management tools
│   ├── ProductImageTools.php           # Product image management tools
│   └── ProductTools.php                # Product management tools
└── ps_mcp_tools.php                    # Main module file
```

### Requirements for Development

-   PHP 8.1+
-   Composer
-   PrestaShop PHP Dev Tools (for code quality checks)

### Code Quality

The project uses:

-   PHPStan for static analysis
-   PHP CS Fixer for code formatting

## Support

For support, please contact: cloudsync-support@prestashop.com

## License

Copyright (c) PrestaShop SA. All Rights Reserved.

This module is proprietary software owned by PrestaShop SA. All intellectual property rights, including copyrights, trademarks, and trade secrets, are reserved by PrestaShop SA.

**This module and its code may NOT be copied, redistributed, modified, or resold without explicit authorization from PrestaShop SA.**

This license grants you a personal, non-exclusive, non-transferable, and non-sublicensable license to use the PS MCP Tools module solely for the operation of your PrestaShop store. Any unauthorized use is strictly prohibited.

For complete license terms and conditions, please refer to the [LICENSE.txt](LICENSE.txt) file.
