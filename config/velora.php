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

    'payments' => [
        'platform_provider' => env('VELORA_PLATFORM_PAYMENT_PROVIDER', 'kashier'),
        'tenant_provider' => env('VELORA_TENANT_PAYMENT_PROVIDER', 'kashier'),
        'drivers' => [
            'kashier' => \App\Infrastructure\Payments\Kashier\KashierGateway::class,
        ],
        'kashier' => [
            'mode' => env('KASHIER_MODE', 'test'),
            'test_api_base_url' => env('KASHIER_TEST_API_BASE_URL', 'https://test-api.kashier.io'),
            'live_api_base_url' => env('KASHIER_LIVE_API_BASE_URL', 'https://api.kashier.io'),
            'test_fep_base_url' => env('KASHIER_TEST_FEP_BASE_URL', 'https://test-fep.kashier.io'),
            'live_fep_base_url' => env('KASHIER_LIVE_FEP_BASE_URL', 'https://fep.kashier.io'),
            'secret_key' => env('KASHIER_SECRET_KEY'),
            'payment_api_key' => env('KASHIER_PAYMENT_API_KEY'),
            'merchant_id' => env('KASHIER_MERCHANT_ID'),
            'merchant_redirect_url' => env('KASHIER_MERCHANT_REDIRECT_URL'),
            'webhook_url' => env('KASHIER_WEBHOOK_URL'),
            'allowed_methods' => env('KASHIER_ALLOWED_METHODS', 'card,wallet'),
            'max_failure_attempts' => (int) env('KASHIER_MAX_FAILURE_ATTEMPTS', 3),
        ],
    ],

    'performance' => [
        'cache_version' => env('VELORA_CACHE_VERSION', 'v1'),
    ],

    'testing' => [
        'central_database' => env('VELORA_TEST_CENTRAL_DATABASE', 'sqlite'),
    ],
];
