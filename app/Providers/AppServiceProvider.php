<?php

namespace App\Providers;

use App\Application\Entitlements\EntitlementService;
use App\Application\Tenancy\TenantProvisioner;
use App\Application\Tenancy\TenantProvisionerContract;
use App\Domain\Tenancy\TenantContext;
use App\Models\Bundle;
use App\Models\Feature;
use App\Models\Module;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
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
        Relation::morphMap([
            'module' => Module::class,
            'feature' => Feature::class,
            'bundle' => Bundle::class,
        ]);

        Blade::if('entitled', function (string $capability): bool {
            $tenant = app(TenantContext::class)->get();

            return $tenant !== null
                && app(EntitlementService::class)->canUse($tenant, $capability);
        });

        Blade::if('featureEntitled', function (string $key): bool {
            $tenant = app(TenantContext::class)->get();

            return $tenant !== null
                && app(EntitlementService::class)->hasFeature($tenant, $key);
        });

        Blade::if('moduleEntitled', function (string $key): bool {
            $tenant = app(TenantContext::class)->get();

            return $tenant !== null
                && app(EntitlementService::class)->hasModule($tenant, $key);
        });
    }
}
