<?php

namespace App\Console\Commands;

use App\Application\Tenancy\TenantProvisioner;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

class ProvisionTenantCommand extends Command
{
    protected $signature = 'tenant:provision
        {tenant : Tenant ULID or slug}';

    protected $description = 'Provision or retry provisioning a VeloraPlus tenant database.';

    public function handle(TenantProvisioner $provisioner): int
    {
        $identifier = $this->argument('tenant');
        $tenant = Tenant::query()
            ->where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->first();

        if ($tenant === null) {
            $this->error('Tenant was not found.');
            return self::FAILURE;
        }

        try {
            $tenant = $provisioner->provision($tenant);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info('Tenant database is ready.');
        $this->line('Tenant ID: '.$tenant->getKey());
        $this->line('Database: '.$tenant->database_name);

        return self::SUCCESS;
    }
}