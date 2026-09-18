<?php

namespace App\Console\Commands;

use App\Application\Tenancy\CreateTenant;
use App\Models\PlatformAccount;
use Illuminate\Console\Command;
use Throwable;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create
        {name : Company display name}
        {slug : Unique tenant slug}
        {ownerEmail : Existing platform account email}
        {--domain= : Optional primary domain}';

    protected $description = 'Create and provision a VeloraPlus tenant.';

    public function handle(CreateTenant $createTenant): int
    {
        $owner = PlatformAccount::query()->where('email', $this->argument('ownerEmail'))->first();

        if ($owner === null) {
            $this->error('Platform account was not found. Run the platform seed or create an account first.');
            return self::FAILURE;
        }

        try {
            $tenant = $createTenant->execute(
                owner: $owner,
                name: $this->argument('name'),
                slug: strtolower($this->argument('slug')),
                domain: $this->option('domain'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info('Tenant created and provisioned successfully.');
        $this->line('Tenant ID: '.$tenant->getKey());
        $this->line('Database: '.$tenant->database_name);
        $this->line('Domain: '.$tenant->domains()->where('is_primary', true)->value('domain'));

        return self::SUCCESS;
    }
}