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

use PrestaShop\Module\PsMcpTools\Webservice\AbstractWebservice;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Product management tools using PrestaShop webservices
 */
class ProductTools extends AbstractWebservice
{
    /**
     * Resource name for products
     */
    private const RESOURCE = 'products';

    /**
     * MCP fields definitions for Product (main fields)
     */
    private const PRODUCT_MCP_FIELDS = [
        'id_manufacturer' => [
            'type' => 'integer',
            'description' => 'Manufacturer ID',
        ],
        'id_supplier' => [
            'type' => 'integer',
            'description' => 'Default supplier ID',
        ],
        'id_category_default' => [
            'type' => 'integer',
            'description' => 'Default category ID',
        ],
        'id_shop_default' => [
            'type' => 'integer',
            'description' => 'Default shop ID',
        ],
        'id_tax_rules_group' => [
            'type' => 'integer',
            'description' => 'Tax rules group ID',
        ],
        'id_default_image' => [
            'type' => 'integer',
            'description' => 'Default (cover) image ID for the product',
        ],
        'name' => [
            'type' => 'string',
            'description' => 'Product name (multilang)',
        ],
        'description' => [
            'type' => 'string',
            'description' => 'Full product description, supports HTML (multilang)',
        ],
        'description_short' => [
            'type' => 'string',
            'description' => 'Short product description (multilang)',
        ],
        'link_rewrite' => [
            'type' => 'string',
            'description' => 'SEO-friendly URL (multilang)',
        ],
        'meta_description' => [
            'type' => 'string',
            'description' => 'SEO meta description (multilang)',
        ],
        'meta_title' => [
            'type' => 'string',
            'description' => 'SEO meta title (multilang)',
        ],
        'meta_keywords' => [
            'type' => 'string',
            'description' => 'SEO meta keywords (multilang)',
        ],
        'available_now' => [
            'type' => 'string',
            'description' => 'Text when product is in stock (multilang)',
        ],
        'available_later' => [
            'type' => 'string',
            'description' => 'Text when product is out of stock (multilang)',
        ],
        'price' => [
            'type' => 'number',
            'description' => 'Product price (tax excluded)',
        ],
        'wholesale_price' => [
            'type' => 'number',
            'description' => 'Wholesale price',
        ],
        'unity' => [
            'type' => 'string',
            'description' => 'Unit type (kg, L, etc.)',
        ],
        'unit_price_ratio' => [
            'type' => 'number',
            'description' => 'Unit price ratio',
        ],
        'additional_shipping_cost' => [
            'type' => 'number',
            'description' => 'Additional shipping cost',
        ],
        'reference' => [
            'type' => 'string',
            'description' => 'Product reference/SKU',
        ],
        'supplier_reference' => [
            'type' => 'string',
            'description' => 'Supplier reference',
        ],
        'location' => [
            'type' => 'string',
            'description' => 'Warehouse location',
        ],
        'width' => [
            'type' => 'number',
            'description' => 'Width',
        ],
        'height' => [
            'type' => 'number',
            'description' => 'Height',
        ],
        'depth' => [
            'type' => 'number',
            'description' => 'Depth',
        ],
        'weight' => [
            'type' => 'number',
            'description' => 'Weight',
        ],
        'ean13' => [
            'type' => 'string',
            'description' => 'EAN-13 barcode',
        ],
        'isbn' => [
            'type' => 'string',
            'description' => 'ISBN code',
        ],
        'upc' => [
            'type' => 'string',
            'description' => 'UPC barcode',
        ],
        'mpn' => [
            'type' => 'string',
            'description' => 'Manufacturer Part Number',
        ],
        'ecotax' => [
            'type' => 'number',
            'description' => 'Eco tax',
        ],
        'minimal_quantity' => [
            'type' => 'integer',
            'description' => 'Minimum quantity for order',
        ],
        'low_stock_threshold' => [
            'type' => 'integer',
            'description' => 'Low stock alert threshold',
        ],
        'low_stock_alert' => [
            'type' => 'boolean',
            'description' => 'Enable low stock alert',
        ],
        'quantity' => [
            'type' => 'integer',
            'description' => 'Available quantity',
        ],
        'active' => [
            'type' => 'boolean',
            'description' => 'Product is active',
        ],
        'available_for_order' => [
            'type' => 'boolean',
            'description' => 'Available for purchase',
        ],
        'available_date' => [
            'type' => 'string',
            'description' => 'Available date (YYYY-MM-DD)',
        ],
        'show_condition' => [
            'type' => 'boolean',
            'description' => 'Show condition on product page',
        ],
        'condition' => [
            'type' => 'string',
            'description' => 'Condition (new, used, refurbished)',
        ],
        'show_price' => [
            'type' => 'boolean',
            'description' => 'Show price on product page',
        ],
        'indexed' => [
            'type' => 'boolean',
            'description' => 'Product is indexed for search',
        ],
        'visibility' => [
            'type' => 'string',
            'description' => 'Visibility (both, catalog, search, none)',
        ],
        'on_sale' => [
            'type' => 'boolean',
            'description' => 'Product is on sale',
        ],
        'online_only' => [
            'type' => 'boolean',
            'description' => 'Available online only',
        ],
        'is_virtual' => [
            'type' => 'boolean',
            'description' => 'Is virtual product (downloadable)',
        ],
        'customizable' => [
            'type' => 'integer',
            'description' => 'Number of customizable text fields',
        ],
        'uploadable_files' => [
            'type' => 'integer',
            'description' => 'Number of uploadable files',
        ],
        'text_fields' => [
            'type' => 'integer',
            'description' => 'Number of text fields',
        ],
        'redirect_type' => [
            'type' => 'string',
            'description' => 'Redirect type when disabled',
        ],
        'id_type_redirected' => [
            'type' => 'integer',
            'description' => 'ID for redirect',
        ],
        'advanced_stock_management' => [
            'type' => 'boolean',
            'description' => 'Use advanced stock management',
        ],
        'pack_stock_type' => [
            'type' => 'integer',
            'description' => 'Pack stock type (0=default, 1=products only, 2=pack only, 3=both)',
        ],
        'state' => [
            'type' => 'integer',
            'description' => 'Product state ID',
        ],
    ];

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_products',
        description: 'Retrieve a list of products from the store using PrestaShop webservices. Default limit is 10 products.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,name,price,reference,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'filter' => ['type' => 'string', 'description' => 'Filter results. Multiple values for same field use pipe: "id=[23|22|21]". Multiple fields use comma: "active=1,id_category_default=5". Operators: "=" (equal), ">" (greater), "<" (less), "!" (not), "[val1|val2]" (in list). Examples: "active=1", "id=[10|20|30]", "price=>100", "active=1,on_sale=1". Default: empty'],
            'sort' => ['type' => 'string', 'description' => 'Sort results (e.g., "id_ASC", "price_DESC", "date_add_DESC") - default: empty'],
            'limit' => ['type' => 'string', 'description' => 'Limit results (e.g., "10" or "0,10") - default: 10'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['display']
    )]
    public function getProducts(
        string $display,
        string $filter = '',
        string $sort = '',
        string $limit = '10',
        ?int $langId = null,
    ): array {
        return $this->getResourceList(self::RESOURCE, $display, $filter, $sort, $limit, $langId);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_product_by_id',
        description: 'Retrieve a specific product by its ID using PrestaShop webservices'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'productId' => ['type' => 'integer', 'description' => 'ID of the product to retrieve'],
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,name,price,reference,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['productId', 'display']
    )]
    public function getProductById(int $productId, string $display, ?int $langId = null): array
    {
        return $this->getResourceById(self::RESOURCE, $productId, $display, $langId);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'search_product',
        description: 'Find products most relevant products based on search terms for a given language.',
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'searchTerms' => ['type' => 'string', 'description' => 'Semicolon separated list of terms to search for.'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
            'limitResults' => ['type' => 'integer', 'description' => 'Maximum number of search results (defaults to 10).'],
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,name,price,reference,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
        ],
        required: ['searchTerms', 'display']
    )]
    public function searchProducts(
        string $searchTerms,
        string $display,
        ?int $langId = null,
        int $limitResults = 10,
    ): array {
        // Use default language if not specified
        if ($langId === null) {
            $langId = (int) \Configuration::get('PS_LANG_DEFAULT');
        }

        // Use PrestaShop's native search to find relevant product IDs
        $searchResult = \Search::find($langId, $searchTerms, 1, $limitResults, 'position', 'desc', false, false);

        if (!is_array($searchResult) || empty($searchResult)) {
            return ['products' => []];
        }

        // Extract product IDs from search results
        $productIds = array_column($searchResult['result'], 'id_product');

        if (empty($productIds)) {
            return ['products' => []];
        }

        // Build filter string with product IDs using OR syntax
        $filter = 'id=[' . implode('|', $productIds) . ']';

        // Retrieve full product details via webservice
        $products = $this->getResourceList(self::RESOURCE, $display, $filter, '', (string) $limitResults, $langId);

        return $products;
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'create_product',
        description: 'Create a product for a given language.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'data' => [
                'type' => 'object',
                'description' => 'Create a new product with these properties',
                'properties' => self::PRODUCT_MCP_FIELDS,
                'additionalProperties' => false,
                'required' => [
                    'price',
                ],
            ],
            'langId' => ['type' => 'integer', 'description' => 'Language of this product.'],
        ],
        required: ['data', 'langId']
    )]
    public function createProduct(array $data, int $langId): \Product
    {
        $product = new \Product(null, false, $langId);
        foreach ($data as $field => $value) {
            $product->$field = $value;
        }
        $validationErrors = $product->validateController();
        if (count($validationErrors) > 0) {
            throw new \InvalidArgumentException(implode(';', $validationErrors));
        }
        $product->save();

        return $product;
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'update_product_by_id',
        description: 'Update an existing product using PrestaShop webservices'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'productId' => ['type' => 'integer', 'description' => 'ID of the product to update'],
            'data' => [
                'type' => 'object',
                'description' => 'Product data to update. For multilang fields, provide an object with language IDs as keys',
                'properties' => self::PRODUCT_MCP_FIELDS,
                'additionalProperties' => false,
            ],
        ],
        required: ['productId', 'data']
    )]
    public function updateProductById(int $productId, array $data): array
    {
        return $this->updateResource(self::RESOURCE, $productId, $data);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_products_by_category',
        description: 'Get all products in a specific category'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'categoryId' => ['type' => 'integer', 'description' => 'ID of the category'],
            'active' => ['type' => 'boolean', 'description' => 'Filter by active status (default: true)'],
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,name,price,reference,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'sort' => ['type' => 'string', 'description' => 'Sort results - default: position_ASC'],
            'limit' => ['type' => 'string', 'description' => 'Limit results - default: 50'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['categoryId', 'display']
    )]
    public function getProductsByCategory(
        int $categoryId,
        string $display,
        bool $active = true,
        string $limit = '50',
        ?int $langId = null,
    ): array {
        $activeValue = $active ? '1' : '0';
        $filter = "id_category_default={$categoryId},active={$activeValue}";

        $products = $this->getResourceList(self::RESOURCE, $display, $filter, '', $limit, $langId);

        if (!empty($products)) {
            // Sort manually by date_add
            usort($products['products'], function ($a, $b) {
                return strtotime($b['date_add']) <=> strtotime($a['date_add']);
            });
        }

        return $products;
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_products_by_manufacturer',
        description: 'Get all products from a specific manufacturer'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'manufacturerId' => ['type' => 'integer', 'description' => 'ID of the manufacturer'],
            'active' => ['type' => 'boolean', 'description' => 'Filter by active status (default: true)'],
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,name,price,reference,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'sort' => ['type' => 'string', 'description' => 'Sort results - default: id_ASC'],
            'limit' => ['type' => 'string', 'description' => 'Limit results - default: 50'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['manufacturerId', 'display']
    )]
    public function getProductsByManufacturer(
        int $manufacturerId,
        string $display,
        bool $active = true,
        string $sort = 'id_ASC',
        string $limit = '50',
        ?int $langId = null,
    ): array {
        $activeValue = $active ? '1' : '0';
        $filter = "id_manufacturer={$manufacturerId},active={$activeValue}";

        return $this->getResourceList(self::RESOURCE, $display, $filter, $sort, $limit, $langId);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_products_by_supplier',
        description: 'Get all products from a specific supplier'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'supplierId' => ['type' => 'integer', 'description' => 'ID of the supplier'],
            'active' => ['type' => 'boolean', 'description' => 'Filter by active status (default: true)'],
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,name,price,reference,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'sort' => ['type' => 'string', 'description' => 'Sort results - default: id_ASC'],
            'limit' => ['type' => 'string', 'description' => 'Limit results - default: 50'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['supplierId', 'display']
    )]
    public function getProductsBySupplier(
        int $supplierId,
        string $display,
        bool $active = true,
        string $sort = 'id_ASC',
        string $limit = '50',
        ?int $langId = null,
    ): array {
        $activeValue = $active ? '1' : '0';
        $filter = "id_supplier={$supplierId},active={$activeValue}";

        return $this->getResourceList(self::RESOURCE, $display, $filter, $sort, $limit, $langId);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_products_on_sale',
        description: 'Get all products currently on sale'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'active' => ['type' => 'boolean', 'description' => 'Filter by active status (default: true)'],
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,name,price,reference,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'limit' => ['type' => 'string', 'description' => 'Limit results - default: 50'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['display']
    )]
    public function getProductsOnSale(
        string $display,
        bool $active = true,
        string $limit = '50',
        ?int $langId = null,
    ): array {
        $activeValue = $active ? '1' : '0';
        $filter = "state={$activeValue}";

        $products = $this->getResourceList(self::RESOURCE, $display, $filter, '', $limit, $langId);

        if (!empty($products)) {
            // Sort manually by date_add
            usort($products['products'], function ($a, $b) {
                return strtotime($b['date_add']) <=> strtotime($a['date_add']);
            });
        }

        return $products;
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_products_by_reference',
        description: 'Search products by reference/SKU'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'reference' => ['type' => 'string', 'description' => 'Product reference to search for. For multiple references (OR search), separate them with pipe | WITHOUT spaces (e.g., "REF001|REF002|REF003"). Use % for wildcard'],
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,name,price,reference,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['reference', 'display']
    )]
    public function getProductsByReference(
        string $reference,
        string $display,
        ?int $langId = null,
    ): array {
        $filter = "reference=[{$reference}]%";

        return $this->getResourceList(self::RESOURCE, $display, $filter, '', '100', $langId);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_product_stock_availables',
        description: 'Get stock information for a specific product'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'productId' => ['type' => 'integer', 'description' => 'ID of the product'],
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,name,price,reference,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['productId', 'display']
    )]
    public function getProductStockAvailables(int $productId, string $display, ?int $langId = null): array
    {
        $filter = "id_product={$productId}";

        return $this->getResourceList('stock_availables', $display, $filter, '', '100', $langId);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_product_combinations',
        description: 'Get all combinations (variants) for a specific product'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'productId' => ['type' => 'integer', 'description' => 'ID of the product'],
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,name,price,reference,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['productId', 'display']
    )]
    public function getProductCombinations(int $productId, string $display, ?int $langId = null): array
    {
        $filter = "id_product={$productId}";

        return $this->getResourceList('combinations', $display, $filter, '', '100', $langId);
    }
}
