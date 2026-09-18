<?php

use App\Models\PlatformAccount;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use App\Application\Tenancy\CreateTenant;
use App\Application\Tenancy\TenantProvisionerContract;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an account can create a tenant with a primary domain and owner membership', function () {
    $owner = PlatformAccount::factory()->create();
    $provisioner = Mockery::mock(TenantProvisionerContract::class);
    $provisioner->shouldReceive('provision')->once()->andReturnUsing(
        function (Tenant $tenant): Tenant {
            $tenant->forceFill([
                'status' => 'active',
                'database_status' => 'ready',
                'database_ready_at' => now(),
            ])->save();

            return $tenant->refresh();
        },
    );
    app()->instance(TenantProvisionerContract::class, $provisioner);

    $tenant = app(CreateTenant::class)->execute(
        owner: $owner,
        name: 'Velora Clinic',
        slug: 'velora-clinic',
        domain: 'velora-clinic.velora.test',
        attributes: [
            'country_code' => 'EG',
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
        ],
    );

    expect($tenant->database_status)->toBe('ready')
        ->and($tenant->slug)->toBe('velora-clinic')
        ->and($tenant->domains()->count())->toBe(1)
        ->and($tenant->memberships()->where('account_id', $owner->getKey())->exists())->toBeTrue();
});

test('tenant membership is unique per account and tenant', function () {
    $owner = PlatformAccount::factory()->create();
    $tenant = Tenant::factory()->create();

    TenantMembership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $owner->getKey(),
    ]);

    expect(fn () => TenantMembership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $owner->getKey(),
    ]))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});