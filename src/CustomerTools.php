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
 * Customer management tools using PrestaShop webservices
 */
class CustomerTools extends AbstractWebservice
{
    /**
     * Resource name for customers
     */
    private const RESOURCE = 'customers';

    /**
     * MCP fields definitions for Customer
     */
    private const CUSTOMER_MCP_FIELDS = [
        'firstname' => [
            'type' => 'string',
            'description' => 'Customer first name',
        ],
        'lastname' => [
            'type' => 'string',
            'description' => 'Customer last name',
        ],
        'email' => [
            'type' => 'string',
            'description' => 'Customer email address (must be unique)',
        ],
        'passwd' => [
            'type' => 'string',
            'description' => 'Customer password (plain text, will be hashed by PrestaShop)',
        ],
        'id_default_group' => [
            'type' => 'integer',
            'description' => 'Default customer group ID (default: 3 for regular customers)',
        ],
        'id_gender' => [
            'type' => 'integer',
            'description' => 'Gender ID (1=Mr, 2=Mrs, 3=Other)',
        ],
        'birthday' => [
            'type' => 'string',
            'description' => 'Date of birth (format: YYYY-MM-DD)',
        ],
        'newsletter' => [
            'type' => 'boolean',
            'description' => 'Whether customer is subscribed to newsletter',
        ],
        'optin' => [
            'type' => 'boolean',
            'description' => 'Whether customer has opted in for partner offers',
        ],
        'active' => [
            'type' => 'boolean',
            'description' => 'Whether the customer account is active',
        ],
        'note' => [
            'type' => 'string',
            'description' => 'Private note about the customer',
        ],
        'id_shop' => [
            'type' => 'integer',
            'description' => 'Shop ID the customer belongs to',
        ],
        'company' => [
            'type' => 'string',
            'description' => 'Customer company name (for B2B)',
        ],
        'siret' => [
            'type' => 'string',
            'description' => 'Company SIRET number (for B2B)',
        ],
        'website' => [
            'type' => 'string',
            'description' => 'Customer website',
        ],
    ];

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_customers',
        description: 'Retrieve a list of customers from the store using PrestaShop webservices. Default limit is 10 customers.'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,email,firstname,lastname,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'filter' => ['type' => 'string', 'description' => 'Filter results. Multiple values for same field use pipe: "id=[5|10|15]". Multiple fields use comma: "active=1,newsletter=1". Operators: "=" (equal), ">" (greater), "<" (less), "!" (not), "[val1|val2]" (in list). Examples: "active=1", "id=[5|10]", "id_default_group=3", "active=1,newsletter=1". Default: empty'],
            'sort' => ['type' => 'string', 'description' => 'Sort results (e.g., "id_ASC", "email_DESC", "date_add_DESC") - default: empty'],
            'limit' => ['type' => 'string', 'description' => 'Limit results (e.g., "10" or "0,10") - default: 10'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['display']
    )]
    public function getCustomers(
        string $display,
        string $filter = '',
        string $sort = '',
        string $limit = '10',
        ?int $langId = null,
    ): array {
        return $this->getResourceList(self::RESOURCE, $display, $filter, $sort, $limit, $langId);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_customer_by_id',
        description: 'Retrieve a specific customer by their ID using PrestaShop webservices'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'customerId' => ['type' => 'integer', 'description' => 'ID of the customer to retrieve'],
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,email,firstname,lastname,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['customerId', 'display']
    )]
    public function getCustomerById(int $customerId, string $display, ?int $langId = null): array
    {
        return $this->getResourceById(self::RESOURCE, $customerId, $display, $langId);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'create_customer',
        description: 'Create a new customer using PrestaShop webservices'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'data' => [
                'type' => 'object',
                'description' => 'Customer data. Email must be unique.',
                'properties' => self::CUSTOMER_MCP_FIELDS,
                'additionalProperties' => false,
                'required' => [
                    'firstname',
                    'lastname',
                    'email',
                    'passwd',
                ],
            ],
        ],
        required: ['data']
    )]
    public function createCustomer(array $data): array
    {
        // Set defaults
        if (!isset($data['active'])) {
            $data['active'] = 1;
        }
        if (!isset($data['id_default_group'])) {
            $data['id_default_group'] = 3; // Default customer group
        }
        if (!isset($data['newsletter'])) {
            $data['newsletter'] = 0;
        }
        if (!isset($data['optin'])) {
            $data['optin'] = 0;
        }
        if (!isset($data['id_shop'])) {
            $data['id_shop'] = (int) \Configuration::get('PS_SHOP_DEFAULT') ?: 1;
        }

        return $this->createResource(self::RESOURCE, $data);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'update_customer_by_id',
        description: 'Update an existing customer using PrestaShop webservices'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'customerId' => ['type' => 'integer', 'description' => 'ID of the customer to update'],
            'data' => [
                'type' => 'object',
                'description' => 'Customer data to update',
                'properties' => self::CUSTOMER_MCP_FIELDS,
                'additionalProperties' => false,
            ],
        ],
        required: ['customerId', 'data']
    )]
    public function updateCustomerById(int $customerId, array $data): array
    {
        return $this->updateResource(self::RESOURCE, $customerId, $data);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'search_customer_by_email',
        description: 'Search for customers by email address. Supports exact match or partial match (LIKE operator).'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'email' => ['type' => 'string', 'description' => 'Email to search for. For multiple emails (OR search), separate them with pipe | WITHOUT spaces (e.g., "email1@test.com|email2@test.com"). Use % for wildcard (e.g., "test%" for emails starting with test)'],
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,email,firstname,lastname,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['email', 'display']
    )]
    public function searchCustomerByEmail(string $email, string $display, ?int $langId = null): array
    {
        $filter = "email=[{$email}]";

        return $this->getResourceList(self::RESOURCE, $display, $filter, '', '100', $langId);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_newsletter_subscribers',
        description: 'Get all customers subscribed to newsletter'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'active' => ['type' => 'boolean', 'description' => 'Filter by active status (default: true)'],
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,email,firstname,lastname,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'sort' => ['type' => 'string', 'description' => 'Sort results - default: email_ASC'],
            'limit' => ['type' => 'string', 'description' => 'Limit results - default: 100'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['display']
    )]
    public function getNewsletterSubscribers(
        string $display,
        bool $active = true,
        string $sort = 'email_ASC',
        string $limit = '100',
        ?int $langId = null,
    ): array {
        $activeValue = $active ? '1' : '0';
        $filter = "newsletter=1,active={$activeValue}";

        return $this->getResourceList(self::RESOURCE, $display, $filter, $sort, $limit, $langId);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_customers_by_group',
        description: 'Get customers belonging to a specific customer group'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'groupId' => ['type' => 'integer', 'description' => 'ID of the customer group (3=Customer, 4=Guest, etc.)'],
            'active' => ['type' => 'boolean', 'description' => 'Filter by active status (default: true)'],
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,email,firstname,lastname,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'sort' => ['type' => 'string', 'description' => 'Sort results - default: id_ASC'],
            'limit' => ['type' => 'string', 'description' => 'Limit results - default: 50'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['groupId', 'display']
    )]
    public function getCustomersByGroup(
        int $groupId,
        string $display,
        bool $active = true,
        string $sort = 'id_ASC',
        string $limit = '50',
        ?int $langId = null,
    ): array {
        $activeValue = $active ? '1' : '0';
        $filter = "id_default_group={$groupId},active={$activeValue}";

        return $this->getResourceList(self::RESOURCE, $display, $filter, $sort, $limit, $langId);
    }

    #[\PhpMcp\Server\Attributes\McpTool(
        name: 'get_recently_registered_customers',
        description: 'Get customers registered within a specific date range'
    )]
    #[\PhpMcp\Server\Attributes\Schema(
        properties: [
            'startDate' => ['type' => 'string', 'description' => 'Start date (format: YYYY-MM-DD HH:MM:SS or YYYY-MM-DD)'],
            'endDate' => ['type' => 'string', 'description' => 'End date (format: YYYY-MM-DD HH:MM:SS or YYYY-MM-DD) - default: now'],
            'display' => ['type' => 'string', 'description' => 'IMPORTANT: Specify only the fields you need to minimize token usage. Use "[field1,field2,field3]" format (e.g., "[id,email,firstname,lastname,active]") to retrieve specific fields. Use "full" only when you need all fields. This significantly reduces response size and token consumption.'],
            'sort' => ['type' => 'string', 'description' => 'Sort results - default: date_add_DESC'],
            'limit' => ['type' => 'string', 'description' => 'Limit results - default: 50'],
            'langId' => ['type' => ['integer', 'null'], 'description' => 'Language ID to filter multilang fields (optional)'],
        ],
        required: ['startDate', 'display']
    )]
    public function getRecentlyRegisteredCustomers(
        string $startDate,
        string $display,
        string $endDate = '',
        string $sort = 'date_add_DESC',
        string $limit = '50',
        ?int $langId = null,
    ): array {
        // Ensure date format includes time
        if (!preg_match('/\d{2}:\d{2}:\d{2}/', $startDate)) {
            $startDate .= ' 00:00:00';
        }

        if ($endDate === '') {
            $endDate = date('Y-m-d H:i:s');
        } elseif (!preg_match('/\d{2}:\d{2}:\d{2}/', $endDate)) {
            $endDate .= ' 23:59:59';
        }

        // Note: date filtering requires date=1 parameter and uses brackets for range
        $params = [
            'display' => $display,
            'date' => '1',
            'sort' => $sort !== '' ? $sort : null,
            'limit' => $limit !== '' ? $limit : null,
        ];

        // Add date filter directly to params (not through parseFilterParams)
        $params['filter[date_add]'] = "[{$startDate},{$endDate}]";

        if ($langId !== null) {
            $params['language'] = $langId;
        }

        // Remove null values
        $params = array_filter($params, function ($value) {
            return $value !== null;
        });

        $result = $this->executeWebserviceRequest('GET', self::RESOURCE, $params);

        return $this->parseWebserviceResponse($result);
    }
}
