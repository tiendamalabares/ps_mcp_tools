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
-   **LanguageTools**: Handle language and localization operations

All tools interact with PrestaShop through the SOAP webservices API, ensuring secure and standardized access to your store data.

## Usage

This module is designed to work with the PS MCP Server module. Once installed, the tools will be available through the MCP interface, allowing LLM applications to interact with your PrestaShop store.

## Development

### Project Structure

```
ps_mcp_tools/
├── src/
│   ├── AbstractWebserviceTools.php  # Base class for webservice tools
│   ├── CustomerTools.php            # Customer management tools
│   ├── LanguageTools.php            # Language management tools
│   ├── OrderTools.php               # Order management tools
│   └── ProductTools.php             # Product management tools
├── tests/                           # Test files
└── ps_mcp_tools.php                 # Main module file
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
