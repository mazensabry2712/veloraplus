<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VeloraPlus Platform Configuration
    |--------------------------------------------------------------------------
    |
    | These values describe the platform-level defaults. Tenant/company
    | configuration is stored separately and must never be read from this
    | file when tenant-specific behavior is required.
    |
    */

    'platform' => [
        'domain' => env('VELORA_PLATFORM_DOMAIN', 'velora.com'),
        'url' => env('VELORA_PLATFORM_URL', 'https://velora.com'),
    ],

    'tenancy' => [
        'base_domain' => env('VELORA_TENANT_BASE_DOMAIN', 'velora.com'),
        'default_scheme' => env('VELORA_TENANT_SCHEME', 'https'),
    ],

    'performance' => [
        'cache_version' => env('VELORA_CACHE_VERSION', 'v1'),
    ],

    'testing' => [
        'central_database' => env('VELORA_TEST_CENTRAL_DATABASE', 'sqlite'),
    ],
];
