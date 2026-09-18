<?php

namespace App\Application\Tenancy;

use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TenantProvisioner implements TenantProvisionerContract
{
    public function __construct(
        private readonly TenantDatabaseManager $databaseManager,
    ) {}

    public function provision(Tenant $tenant): Tenant
    {
        if ($tenant->database_status === 'ready') {
            try {
                $this->databaseManager->connect($tenant);

                if (Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('tenant_runtime')) {
                    $this->ensureTenantRuntime($tenant);

                    if (Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('company_settings')) {
                        $this->ensureCompanySettings($tenant);
                    }

                    return $tenant;
                }
            } finally {
                $this->databaseManager->disconnect();
            }
        }

        $tenant->forceFill([
            'database_status' => 'provisioning',
            'database_provisioning_error' => null,
        ])->save();

        try {
            $this->createDatabase($tenant);
            $this->databaseManager->connect($tenant);

            $exitCode = Artisan::call('migrate', [
                '--database' => TenantDatabaseManager::CONNECTION,
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                throw new RuntimeException(Artisan::output());
            }

            if (! Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('tenant_runtime')) {
                throw new RuntimeException('Tenant baseline migration completed without creating the tenant_runtime table.');
            }

            $this->ensureTenantRuntime($tenant);

            if (Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('company_settings')) {
                $this->ensureCompanySettings($tenant);
            }

            $tenant->forceFill([
                'status' => 'active',
                'database_status' => 'ready',
                'database_ready_at' => now(),
                'database_provisioning_error' => null,
            ])->save();

            return $tenant->refresh();
        } catch (Throwable $exception) {
            $tenant->forceFill([
                'status' => 'provisioning',
                'database_status' => 'failed',
                'database_provisioning_error' => Str::limit($exception->getMessage(), 2000),
            ])->save();
            throw $exception;
        } finally {
            $this->databaseManager->disconnect();
        }
    }
    private function ensureTenantRuntime(Tenant $tenant): void
    {
        $table = DB::connection(TenantDatabaseManager::CONNECTION)->table('tenant_runtime');
        $runtime = $table->where('tenant_id', $tenant->getKey())->first();

        if ($runtime === null) {
            $now = now();

            $table->insert([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenant->getKey(),
                'schema_version' => '1.0',
                'provisioned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        if ($runtime->provisioned_at === null) {
            $table->where('tenant_id', $tenant->getKey())->update([
                'provisioned_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureCompanySettings(Tenant $tenant): void
    {
        $table = DB::connection(TenantDatabaseManager::CONNECTION)->table('company_settings');
        $now = now();

        $settings = [
            'company.name' => [$tenant->name, 'string'],
            'company.legal_name' => [$tenant->legal_name, 'string'],
            'company.country_code' => [$tenant->country_code, 'string'],
            'company.default_currency' => [$tenant->default_currency, 'string'],
            'company.timezone' => [$tenant->timezone, 'string'],
            'company.locale' => [$tenant->locale, 'string'],
        ];

        foreach ($settings as $key => [$value, $type]) {
            if ($table->where('key', $key)->exists()) {
                continue;
            }

            $table->insert([
                'id' => (string) Str::ulid(),
                'key' => $key,
                'value' => $value,
                'type' => $type,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function createDatabase(Tenant $tenant): void
    {
        $connection = DB::connection('central');

        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('Tenant database provisioning currently requires MySQL.');
        }

        $name = $tenant->database_name;

        if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new RuntimeException('Invalid tenant database name.');
        }

        $quotedName = '`'.str_replace('`', '``', $name).'`';
        $connection->statement(
            'CREATE DATABASE IF NOT EXISTS '.$quotedName.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }
}