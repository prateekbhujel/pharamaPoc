<?php

namespace App\Modules\Docs\Services;

class SwaggerService
{
    public function build(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'pharamaPOC API',
                'version' => 'v1',
                'description' => 'Session-based API for login, direct sales/report exports, pharmacy CRUD, and sales CRUD.',
            ],
            'servers' => [
                [
                    'url' => rtrim(config('app.url', 'http://127.0.0.1:8000'), '/'),
                    'description' => 'Laravel application URL',
                ],
            ],
            'tags' => [
                ['name' => 'Auth'],
                ['name' => 'Reporting'],
                ['name' => 'Pharmacies'],
                ['name' => 'Sales'],
            ],
            'paths' => [
                '/api/v1/auth/login' => [
                    'post' => [
                        'tags' => ['Auth'],
                        'summary' => 'Sign in with username or email.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/LoginRequest',
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Authenticated session.',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/SessionUserEnvelope',
                                        ],
                                    ],
                                ],
                            ],
                            '422' => [
                                'description' => 'Validation failed.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/auth/me' => [
                    'get' => [
                        'tags' => ['Auth'],
                        'summary' => 'Get the signed-in user.',
                        'security' => [['cookieAuth' => []]],
                        'responses' => [
                            '200' => [
                                'description' => 'Current session user.',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/SessionUserEnvelope',
                                        ],
                                    ],
                                ],
                            ],
                            '401' => [
                                'description' => 'Unauthenticated.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/auth/logout' => [
                    'post' => [
                        'tags' => ['Auth'],
                        'summary' => 'Sign out.',
                        'security' => [['cookieAuth' => []]],
                        'responses' => [
                            '200' => [
                                'description' => 'Signed out.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/reporting/options' => [
                    'get' => [
                        'tags' => ['Reporting'],
                        'summary' => 'Load dashboard filters, stats, and recent exports.',
                        'security' => [['cookieAuth' => []]],
                        'responses' => [
                            '200' => [
                                'description' => 'Dashboard payload.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/reporting/preview' => [
                    'get' => [
                        'tags' => ['Reporting'],
                        'summary' => 'Preview report rows.',
                        'security' => [['cookieAuth' => []]],
                        'parameters' => $this->reportingParameters(includeFormat: false, includePagination: true),
                        'responses' => [
                            '200' => [
                                'description' => 'Preview rows with pagination.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/reporting/export-direct' => [
                    'get' => [
                        'tags' => ['Reporting'],
                        'summary' => 'Directly download a report as Excel or CSV.',
                        'security' => [['cookieAuth' => []]],
                        'parameters' => $this->reportingParameters(includeFormat: true, includePagination: false),
                        'responses' => [
                            '200' => [
                                'description' => 'File download response.',
                            ],
                            '422' => [
                                'description' => 'Validation error.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/reporting/exports' => [
                    'post' => [
                        'tags' => ['Reporting'],
                        'summary' => 'Create a background report export and poll its progress.',
                        'security' => [['cookieAuth' => []]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/ExportRequest',
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Instant export finished.',
                            ],
                            '202' => [
                                'description' => 'Export queued or processing.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/reporting/exports/{publicId}' => [
                    'get' => [
                        'tags' => ['Reporting'],
                        'summary' => 'Get export status.',
                        'security' => [['cookieAuth' => []]],
                        'parameters' => [
                            [
                                'name' => 'publicId',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'string'],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Export metadata.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/reporting/exports/{publicId}/download' => [
                    'get' => [
                        'tags' => ['Reporting'],
                        'summary' => 'Download a completed export.',
                        'security' => [['cookieAuth' => []]],
                        'parameters' => [
                            [
                                'name' => 'publicId',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'string'],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'CSV or XLSX file stream.',
                            ],
                            '409' => [
                                'description' => 'Export is not ready yet.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/pharmacies' => [
                    'get' => [
                        'tags' => ['Pharmacies'],
                        'summary' => 'List pharmacies.',
                        'security' => [['cookieAuth' => []]],
                        'parameters' => [
                            ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'tenant_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'hospital_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Paginated pharmacy list.',
                            ],
                        ],
                    ],
                    'post' => [
                        'tags' => ['Pharmacies'],
                        'summary' => 'Create a pharmacy.',
                        'security' => [['cookieAuth' => []]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/PharmacyUpsertRequest',
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Created pharmacy.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/pharmacies/export' => [
                    'get' => [
                        'tags' => ['Pharmacies'],
                        'summary' => 'Directly download the pharmacy list as Excel or CSV.',
                        'security' => [['cookieAuth' => []]],
                        'parameters' => [
                            ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'tenant_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'hospital_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'format', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['csv', 'xlsx']]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'File download response.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/pharmacies/{pharmacyId}' => [
                    'put' => [
                        'tags' => ['Pharmacies'],
                        'summary' => 'Update a pharmacy.',
                        'security' => [['cookieAuth' => []]],
                        'parameters' => [
                            [
                                'name' => 'pharmacyId',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'integer'],
                            ],
                        ],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/PharmacyUpsertRequest',
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Updated pharmacy.',
                            ],
                        ],
                    ],
                    'delete' => [
                        'tags' => ['Pharmacies'],
                        'summary' => 'Delete a pharmacy with guard rails.',
                        'security' => [['cookieAuth' => []]],
                        'parameters' => [
                            [
                                'name' => 'pharmacyId',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'integer'],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Deleted pharmacy.',
                            ],
                            '422' => [
                                'description' => 'Delete blocked by validation guard.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/sales' => [
                    'get' => [
                        'tags' => ['Sales'],
                        'summary' => 'List sales with pagination and filters.',
                        'security' => [['cookieAuth' => []]],
                        'parameters' => $this->salesParameters(includeFormat: false, includePagination: true),
                        'responses' => [
                            '200' => [
                                'description' => 'Paginated sales list.',
                            ],
                        ],
                    ],
                    'post' => [
                        'tags' => ['Sales'],
                        'summary' => 'Create a new sale row.',
                        'security' => [['cookieAuth' => []]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/SaleUpsertRequest',
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Created sale.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/sales/export' => [
                    'get' => [
                        'tags' => ['Sales'],
                        'summary' => 'Directly download filtered sales as Excel or CSV.',
                        'security' => [['cookieAuth' => []]],
                        'parameters' => $this->salesParameters(includeFormat: true, includePagination: false),
                        'responses' => [
                            '200' => [
                                'description' => 'File download response.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/sales/template' => [
                    'get' => [
                        'tags' => ['Sales'],
                        'summary' => 'Download a sample sales CSV template.',
                        'security' => [['cookieAuth' => []]],
                        'responses' => [
                            '200' => [
                                'description' => 'CSV template download.',
                            ],
                        ],
                    ],
                ],
                '/api/v1/sales/{saleItemId}' => [
                    'put' => [
                        'tags' => ['Sales'],
                        'summary' => 'Update a sale row.',
                        'security' => [['cookieAuth' => []]],
                        'parameters' => [
                            [
                                'name' => 'saleItemId',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'integer'],
                            ],
                        ],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/SaleUpsertRequest',
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Updated sale.',
                            ],
                        ],
                    ],
                    'delete' => [
                        'tags' => ['Sales'],
                        'summary' => 'Delete a sale row.',
                        'security' => [['cookieAuth' => []]],
                        'parameters' => [
                            [
                                'name' => 'saleItemId',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'integer'],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Deleted sale.',
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'cookieAuth' => [
                        'type' => 'apiKey',
                        'in' => 'cookie',
                        'name' => config('session.cookie', 'laravel_session'),
                    ],
                ],
                'schemas' => [
                    'LoginRequest' => [
                        'type' => 'object',
                        'required' => ['login', 'password'],
                        'properties' => [
                            'login' => ['type' => 'string'],
                            'password' => ['type' => 'string'],
                            'remember' => ['type' => 'boolean'],
                        ],
                    ],
                    'SessionUserEnvelope' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'integer'],
                                    'name' => ['type' => 'string'],
                                    'username' => ['type' => 'string', 'nullable' => true],
                                    'email' => ['type' => 'string', 'format' => 'email'],
                                    'role' => ['type' => 'string'],
                                    'is_active' => ['type' => 'boolean'],
                                    'organization' => [
                                        'type' => 'object',
                                        'nullable' => true,
                                        'properties' => [
                                            'id' => ['type' => 'integer'],
                                            'name' => ['type' => 'string'],
                                            'code' => ['type' => 'string'],
                                        ],
                                    ],
                                    'hospital' => [
                                        'type' => 'object',
                                        'nullable' => true,
                                        'properties' => [
                                            'id' => ['type' => 'integer'],
                                            'name' => ['type' => 'string'],
                                            'code' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'ExportRequest' => [
                        'type' => 'object',
                        'required' => ['date_from', 'date_to', 'format'],
                        'properties' => [
                            'date_from' => ['type' => 'string', 'format' => 'date'],
                            'date_to' => ['type' => 'string', 'format' => 'date'],
                            'tenant_id' => ['type' => 'integer', 'nullable' => true],
                            'hospital_id' => ['type' => 'integer', 'nullable' => true],
                            'pharmacy_id' => ['type' => 'integer', 'nullable' => true],
                            'category_id' => ['type' => 'integer', 'nullable' => true],
                            'supplier_id' => ['type' => 'integer', 'nullable' => true],
                            'payment_status' => ['type' => 'string', 'nullable' => true],
                            'cold_chain' => ['type' => 'boolean', 'nullable' => true],
                            'format' => ['type' => 'string', 'enum' => ['csv', 'xlsx']],
                        ],
                    ],
                    'PharmacyUpsertRequest' => [
                        'type' => 'object',
                        'required' => ['hospital_id', 'code', 'name', 'license_number', 'contact_email', 'area', 'city', 'district', 'province', 'postal_code', 'email_domain'],
                        'properties' => [
                            'hospital_id' => ['type' => 'integer'],
                            'code' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                            'license_number' => ['type' => 'string'],
                            'contact_email' => ['type' => 'string', 'format' => 'email'],
                            'area' => ['type' => 'string'],
                            'city' => ['type' => 'string'],
                            'district' => ['type' => 'string'],
                            'province' => ['type' => 'string'],
                            'postal_code' => ['type' => 'string'],
                            'email_domain' => ['type' => 'string'],
                            'seed_demo_sale' => ['type' => 'boolean'],
                        ],
                    ],
                    'SaleUpsertRequest' => [
                        'type' => 'object',
                        'required' => ['pharmacy_id', 'medicine_id', 'payment_method', 'payment_status', 'sold_at', 'quantity', 'unit_price'],
                        'properties' => [
                            'pharmacy_id' => ['type' => 'integer'],
                            'medicine_id' => ['type' => 'integer'],
                            'invoice_number' => ['type' => 'string', 'nullable' => true],
                            'payment_method' => ['type' => 'string', 'enum' => ['cash', 'card', 'wallet', 'insurance-claim', 'card-plus-cash', 'wallet-plus-cash', 'reversal']],
                            'payment_status' => ['type' => 'string', 'enum' => ['paid', 'insurance', 'partial', 'void']],
                            'sold_at' => ['type' => 'string', 'format' => 'date-time'],
                            'batch_number' => ['type' => 'string', 'nullable' => true],
                            'quantity' => ['type' => 'integer'],
                            'unit_price' => ['type' => 'number'],
                            'discount_amount' => ['type' => 'number', 'nullable' => true],
                            'tax_amount' => ['type' => 'number', 'nullable' => true],
                            'expires_at' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function reportingParameters(bool $includeFormat, bool $includePagination): array
    {
        $parameters = [
            ['name' => 'date_from', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'date']],
            ['name' => 'date_to', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'date']],
            ['name' => 'tenant_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'hospital_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'pharmacy_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'category_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'supplier_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'payment_status', 'in' => 'query', 'schema' => ['type' => 'string']],
            ['name' => 'cold_chain', 'in' => 'query', 'schema' => ['type' => 'boolean']],
        ];

        if ($includeFormat) {
            $parameters[] = ['name' => 'format', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['csv', 'xlsx']]];
        }

        if ($includePagination) {
            $parameters[] = ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']];
            $parameters[] = ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']];
        }

        return $parameters;
    }

    private function salesParameters(bool $includeFormat, bool $includePagination): array
    {
        $parameters = [
            ['name' => 'date_from', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'date']],
            ['name' => 'date_to', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'date']],
            ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
            ['name' => 'tenant_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'hospital_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'pharmacy_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'medicine_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'payment_status', 'in' => 'query', 'schema' => ['type' => 'string']],
            ['name' => 'payment_method', 'in' => 'query', 'schema' => ['type' => 'string']],
        ];

        if ($includeFormat) {
            $parameters[] = ['name' => 'format', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['csv', 'xlsx']]];
        }

        if ($includePagination) {
            $parameters[] = ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']];
            $parameters[] = ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']];
        }

        return $parameters;
    }
}
