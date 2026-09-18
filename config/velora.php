<?php

return [
    'platform' => [
        'domain' => env('VELORA_PLATFORM_DOMAIN', 'velora.com'),
        'url' => env('VELORA_PLATFORM_URL', 'https://velora.com'),
    ],

    'tenancy' => [
        'base_domain' => env('VELORA_TENANT_BASE_DOMAIN', 'velora.com'),
        'default_scheme' => env('VELORA_TENANT_SCHEME', 'https'),
        'database_prefix' => env('VELORA_TENANT_DATABASE_PREFIX', 'veloraplus_tenant_'),
    ],

    'performance' => [
        'cache_version' => env('VELORA_CACHE_VERSION', 'v1'),
    ],

    'testing' => [
        'central_database' => env('VELORA_TEST_CENTRAL_DATABASE', 'sqlite'),
    ],
];