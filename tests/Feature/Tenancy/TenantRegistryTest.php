<?php

use App\Models\PlatformAccount;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Application\Tenancy\CreateTenant;
use App\Application\Tenancy\TenantProvisioner;
use Mockery;

uses(RefreshDatabase::class);

test('an account can create a tenant with a primary domain and owner membership', function () {
    $owner = PlatformAccount::factory()->create();
    $provisioner = Mockery::mock(TenantProvisioner::class);
    $provisioner->shouldReceive('provision')->once()->andReturnUsing(
        fn (Tenant $tenant): Tenant => $tenant,
    );
    app()->instance(TenantProvisioner::class, $provisioner);

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