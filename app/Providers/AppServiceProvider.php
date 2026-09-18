<?php

namespace App\Providers;

use App\Application\Tenancy\TenantProvisioner;
use App\Application\Tenancy\TenantProvisionerContract;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(TenantProvisionerContract::class, TenantProvisioner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
