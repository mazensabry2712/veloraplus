<?php

use App\Domain\Tenancy\TenantResolver;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tenant resolver normalizes hostnames', function () {
    expect(app(TenantResolver::class)->normalize('  BOOKING.VELORA.TEST. '))->toBe('booking.velora.test');
});

test('tenant resolver resolves an active subdomain', function () {
    $tenant = Tenant::factory()->create();
    TenantDomain::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'domain' => 'acme.velora.test',
        'type' => 'subdomain',
        'status' => 'active',
    ]);

    expect(app(TenantResolver::class)->resolve('ACME.VELORA.TEST'))->toBeInstanceOf(Tenant::class)
        ->and(app(TenantResolver::class)->resolve('ACME.VELORA.TEST')->getKey())->toBe($tenant->getKey());
});

test('tenant resolver rejects unverified custom domains', function () {
    $tenant = Tenant::factory()->create();
    TenantDomain::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'domain' => 'app.customer.example',
        'type' => 'custom',
        'status' => 'active',
        'verified_at' => null,
    ]);

    expect(app(TenantResolver::class)->resolve('app.customer.example'))->toBeNull();
});

test('tenant resolver rejects inactive domains', function () {
    $tenant = Tenant::factory()->create();
    TenantDomain::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'domain' => 'old.velora.test',
        'type' => 'subdomain',
        'status' => 'disabled',
    ]);

    expect(app(TenantResolver::class)->resolve('old.velora.test'))->toBeNull();
});