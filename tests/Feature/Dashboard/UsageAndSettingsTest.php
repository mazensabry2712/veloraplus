<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Application\Entitlements\EntitlementService;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Feature;
use App\Models\Module;
use App\Models\PaymentProviderAccount;
use App\Models\PlatformAccount;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantEntitlement;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function usageSettingsTenant(
    string $slug,
    string $domain,
): Tenant {
    $tenant = Tenant::factory()->create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'country_code' => 'EG',
        'default_currency' => 'EGP',
        'timezone' => 'Africa/Cairo',
        'locale' => 'en',
        'status' => 'active',
        'database_status' => 'ready',
    ]);

    TenantDomain::create([
        'tenant_id' => $tenant->getKey(),
        'domain' => $domain,
        'type' => 'subdomain',
        'is_primary' => true,
        'status' => 'active',
        'verified_at' => now(),
    ]);

    return $tenant;
}

function usageSettingsMember(Tenant $tenant, string $role): PlatformAccount
{
    $account = PlatformAccount::factory()->create();

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $account->getKey(),
        'role_key' => $role,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    app(TenantRbacBootstrapper::class)->bootstrapForTenant($tenant);

    return $account;
}

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');
});

test('authorized tenant member can view entitlement usage and limits', function (): void {
    $tenant = usageSettingsTenant('usage-tenant', 'usage-tenant.velora.test');
    $owner = usageSettingsMember($tenant, 'owner');

    $module = Module::factory()->create([
        'key' => 'booking',
        'name' => 'Booking',
        'status' => 'active',
        'is_core' => false,
    ]);

    $feature = Feature::factory()->for($module)->create([
        'key' => 'booking.staff',
        'name' => 'Staff',
        'status' => 'active',
    ]);

    app(EntitlementService::class)->grant($tenant, $feature, [
        'quantity' => 25,
    ]);

    $this->actingAs($owner)
        ->get('https://usage-tenant.velora.test/dashboard/usage')
        ->assertOk()
        ->assertSee('Staff', false)
        ->assertSee('booking.staff', false)
        ->assertSee('25', false)
        ->assertSee('Active', false);
});

test('tenant member without settings view permission cannot view usage', function (): void {
    $tenant = usageSettingsTenant('usage-staff', 'usage-staff.velora.test');
    $staff = usageSettingsMember($tenant, 'staff');

    $this->actingAs($staff)
        ->get('https://usage-staff.velora.test/dashboard/usage')
        ->assertForbidden();
});

test('usage is isolated by the current tenant context', function (): void {
    $tenantA = usageSettingsTenant('usage-a', 'usage-a.velora.test');
    $tenantB = usageSettingsTenant('usage-b', 'usage-b.velora.test');

    $ownerA = usageSettingsMember($tenantA, 'owner');
    usageSettingsMember($tenantB, 'owner');

    $moduleA = Module::factory()->create([
        'key' => 'booking-a',
        'name' => 'Booking A',
        'status' => 'active',
        'is_core' => false,
    ]);

    $featureA = Feature::factory()->for($moduleA)->create([
        'key' => 'booking-a.limits',
        'name' => 'Tenant A Limits',
        'status' => 'active',
    ]);

    TenantEntitlement::query()->create([
        'tenant_id' => $tenantA->getKey(),
        'catalog_type' => $featureA->getMorphClass(),
        'catalog_key' => $featureA->key,
        'status' => 'active',
        'source' => 'manual',
        'quantity' => 7,
    ]);

    $this->actingAs($ownerA)
        ->get('https://usage-a.velora.test/dashboard/usage')
        ->assertOk()
        ->assertSee('Tenant A Limits', false)
        ->assertDontSee('Tenant B Limits', false);
});

test('authorized tenant owner can configure payment integration without exposing credentials', function (): void {
    $tenant = usageSettingsTenant('settings-tenant', 'settings-tenant.velora.test');
    $owner = usageSettingsMember($tenant, 'owner');

    $this->actingAs($owner)
        ->get('https://settings-tenant.velora.test/dashboard/settings')
        ->assertOk()
        ->assertSee('Tenant Payment Integration', false)
        ->assertDontSee('SECRET-SETTINGS', false);

    $this->actingAs($owner)
        ->put('https://settings-tenant.velora.test/dashboard/settings/payment', [
            'provider' => 'kashier',
            'account_reference' => 'SETTINGS-MAIN',
            'status' => 'active',
            'credentials' => [
                'merchant_id' => 'MID-SETTINGS',
                'secret_key' => 'SECRET-SETTINGS',
                'payment_api_key' => 'API-SETTINGS',
                'merchant_redirect_url' => 'https://settings.example.test/return',
                'webhook_url' => 'https://settings.example.test/webhook',
            ],
        ])
        ->assertRedirect('/dashboard/settings')
        ->assertSessionHas('status', 'Tenant payment integration updated successfully.');

    $account = PaymentProviderAccount::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('provider', 'kashier')
        ->firstOrFail();

    expect($account->account_reference)->toBe('SETTINGS-MAIN')
        ->and($account->status)->toBe('active')
        ->and($account->getRawOriginal('encrypted_credentials'))->not->toContain('SECRET-SETTINGS');

    $this->actingAs($owner)
        ->get('https://settings-tenant.velora.test/dashboard/settings')
        ->assertOk()
        ->assertSee('SETTINGS-MAIN', false)
        ->assertDontSee('SECRET-SETTINGS', false)
        ->assertDontSee('API-SETTINGS', false);
});

test('staff member without settings management cannot change payment integration', function (): void {
    $tenant = usageSettingsTenant('settings-staff', 'settings-staff.velora.test');
    $staff = usageSettingsMember($tenant, 'staff');

    $this->actingAs($staff)
        ->put('https://settings-staff.velora.test/dashboard/settings/payment', [
            'provider' => 'kashier',
            'account_reference' => 'STAFF-CANNOT',
            'credentials' => [
                'secret_key' => 'SECRET',
            ],
        ])
        ->assertForbidden();

    expect(PaymentProviderAccount::query()->where('tenant_id', $tenant->getKey())->exists())->toBeFalse();
});

test('payment integration settings stay isolated between tenants', function (): void {
    $tenantA = usageSettingsTenant('settings-a', 'settings-a.velora.test');
    $tenantB = usageSettingsTenant('settings-b', 'settings-b.velora.test');
    $ownerA = usageSettingsMember($tenantA, 'owner');
    $ownerB = usageSettingsMember($tenantB, 'owner');

    $this->actingAs($ownerB)
        ->put('https://settings-b.velora.test/dashboard/settings/payment', [
            'provider' => 'kashier',
            'account_reference' => 'TENANT-B',
            'credentials' => [
                'merchant_id' => 'MID-B',
                'secret_key' => 'SECRET-B',
            ],
        ])
        ->assertRedirect('/dashboard/settings');

    $this->actingAs($ownerA)
        ->get('https://settings-a.velora.test/dashboard/settings')
        ->assertOk()
        ->assertSee('Not configured', false)
        ->assertDontSee('TENANT-B', false);

    expect(PaymentProviderAccount::query()
        ->where('tenant_id', $tenantA->getKey())
        ->exists())->toBeFalse();
});
