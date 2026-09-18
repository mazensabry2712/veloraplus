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
            return $tenant;
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