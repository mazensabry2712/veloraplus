<?php

use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Tenant;
use Illuminate\Support\Facades\Config;

test('tenant database configuration is derived from the tenant registry', function () {
    $tenant = new Tenant([
        'database_name' => 'veloraplus_tenant_01test',
        'database_host' => '127.0.0.2',
        'database_port' => 3307,
    ]);
    app(TenantDatabaseManager::class)->configure($tenant);
    $config = Config::get('database.connections.tenant');

    expect($config['database'])->toBe('veloraplus_tenant_01test')
        ->and($config['host'])->toBe('127.0.0.2')
        ->and($config['port'])->toBe(3307);
});