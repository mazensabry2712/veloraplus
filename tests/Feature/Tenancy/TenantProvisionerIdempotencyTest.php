<?php

use App\Application\Tenancy\TenantProvisioner;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->originalTenantTemplate = config('database.connections.tenant_template');

    config([
        'database.connections.tenant_template' => [
            'driver' => 'sqlite',
            'url' => null,
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],
    ]);
});

afterEach(function () {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');

    config([
        'database.connections.tenant_template' => $this->originalTenantTemplate,
    ]);
});

test('tenant provisioning is idempotent when company settings already exist', function () {
    $path = storage_path('framework/testing/tenant-provision-'.Str::ulid().'.sqlite');

    File::ensureDirectoryExists(dirname($path));
    touch($path);

    try {
        config(['database.connections.tenant_template.database' => $path]);

        $tenant = new Tenant;
        $tenant->forceFill([
            'id' => (string) Str::ulid(),
            'name' => 'Velora Clinic',
            'legal_name' => 'Velora Clinic LLC',
            'country_code' => 'EG',
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
            'locale' => 'ar',
            'database_name' => $path,
            'database_status' => 'ready',
        ]);

        $manager = app(TenantDatabaseManager::class);
        $manager->connect($tenant);

        expect(Artisan::call('migrate', [
            '--database' => TenantDatabaseManager::CONNECTION,
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]))->toBe(0);

        expect(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('company_settings'))->toBeTrue();

        DB::connection(TenantDatabaseManager::CONNECTION)->table('company_settings')->insert([
            'id' => (string) Str::ulid(),
            'key' => 'company.legal_name',
            'value' => 'Existing Legal Name',
            'type' => 'string',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager->disconnect();

        app(TenantProvisioner::class)->provision($tenant);
        app(TenantProvisioner::class)->provision($tenant);

        $manager->connect($tenant);

        $settings = DB::connection(TenantDatabaseManager::CONNECTION)
            ->table('company_settings')
            ->pluck('value', 'key')
            ->all();

        expect($settings)
            ->toHaveCount(6)
            ->and($settings['company.legal_name'])->toBe('Existing Legal Name')
            ->and($settings['company.name'])->toBe('Velora Clinic')
            ->and($settings['company.default_currency'])->toBe('EGP');

        $manager->disconnect();
    } finally {
        DB::purge(TenantDatabaseManager::CONNECTION);

        if (is_file($path)) {
            unlink($path);
        }
    }
});
