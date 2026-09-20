<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\CompanySetting;
use App\Models\PlatformAccount;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->originalTenantTemplate = config('database.connections.tenant_template');
    $this->companyProfileCompletionPaths = [];
});

function companyProfileCompletionPath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/company-profile-completion-'.Str::ulid().'.sqlite';
    touch($path);

    config([
        'database.connections.tenant_template' => [
            'driver' => 'sqlite',
            'url' => null,
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],
    ]);

    return $path;
}

function makeCompanyProfileCompletionTenant(string $path): Tenant
{
    $tenant = Tenant::factory()->create([
        'name' => 'Completion Tenant',
        'legal_name' => 'Completion Tenant LLC',
        'slug' => 'completion-tenant',
        'industry' => 'healthcare',
        'business_type' => 'clinic',
        'country_code' => 'EG',
        'default_currency' => 'EGP',
        'timezone' => 'Africa/Cairo',
        'locale' => 'en',
        'database_name' => $path,
        'database_host' => null,
        'database_port' => null,
        'status' => 'active',
        'database_status' => 'ready',
    ]);

    TenantDomain::create([
        'tenant_id' => $tenant->getKey(),
        'domain' => 'completion-tenant.velora.test',
        'type' => 'subdomain',
        'is_primary' => true,
        'status' => 'active',
        'verified_at' => now(),
    ]);

    app(TenantDatabaseManager::class)->connect($tenant);

    try {
        expect(Artisan::call('migrate', [
            '--database' => TenantDatabaseManager::CONNECTION,
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]))->toBe(0);
    } finally {
        app(TenantDatabaseManager::class)->disconnect();
    }

    return $tenant;
}

function addCompletionOwner(Tenant $tenant): PlatformAccount
{
    $account = PlatformAccount::factory()->create();

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $account->getKey(),
        'role_key' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    app(TenantRbacBootstrapper::class)->bootstrapForTenant($tenant);

    return $account;
}

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');

    config([
        'database.connections.tenant_template' => $this->originalTenantTemplate,
    ]);

    foreach ($this->companyProfileCompletionPaths as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('company profile supports the documented contact and business identity fields', function (): void {
    $path = companyProfileCompletionPath();
    $this->companyProfileCompletionPaths[] = $path;

    $tenant = makeCompanyProfileCompletionTenant($path);
    $owner = addCompletionOwner($tenant);

    $this->actingAs($owner)
        ->post('http://completion-tenant.velora.test/dashboard/company/profile', [
            'name' => 'Updated Completion Clinic',
            'legal_name' => 'Updated Completion Clinic LLC',
            'industry' => 'healthcare',
            'business_type' => 'medical-clinic',
            'country_code' => 'EG',
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
            'locale' => 'ar-EG',
            'phone' => '+201001112233',
            'email' => 'hello@example.test',
            'website' => 'https://example.test',
            'city' => 'Cairo',
            'address' => '12 Main Street',
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Company profile updated successfully.');

    $tenant->refresh();

    expect($tenant->name)->toBe('Updated Completion Clinic')
        ->and($tenant->business_type)->toBe('medical-clinic')
        ->and($tenant->phone)->toBe('+201001112233')
        ->and($tenant->email)->toBe('hello@example.test')
        ->and($tenant->website)->toBe('https://example.test')
        ->and($tenant->city)->toBe('Cairo')
        ->and($tenant->address)->toBe('12 Main Street');

    app(TenantDatabaseManager::class)->connect($tenant);

    try {
        expect(CompanySetting::query()->where('key', 'company.business_type')->value('value'))->toBe('medical-clinic')
            ->and(CompanySetting::query()->where('key', 'company.phone')->value('value'))->toBe('+201001112233')
            ->and(CompanySetting::query()->where('key', 'company.email')->value('value'))->toBe('hello@example.test')
            ->and(CompanySetting::query()->where('key', 'company.website')->value('value'))->toBe('https://example.test')
            ->and(CompanySetting::query()->where('key', 'company.city')->value('value'))->toBe('Cairo')
            ->and(CompanySetting::query()->where('key', 'company.address')->value('value'))->toBe('12 Main Street');
    } finally {
        app(TenantDatabaseManager::class)->disconnect();
    }
});

test('company profile rejects malformed contact fields', function (): void {
    $path = companyProfileCompletionPath();
    $this->companyProfileCompletionPaths[] = $path;

    $tenant = makeCompanyProfileCompletionTenant($path);
    $owner = addCompletionOwner($tenant);

    $this->actingAs($owner)
        ->post('http://completion-tenant.velora.test/dashboard/company/profile', [
            'name' => 'Invalid Contact Clinic',
            'legal_name' => null,
            'industry' => null,
            'business_type' => null,
            'country_code' => 'EG',
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
            'locale' => 'en',
            'phone' => str_repeat('1', 51),
            'email' => 'not-an-email',
            'website' => 'not-a-url',
            'city' => 'Cairo',
            'address' => 'Address',
        ])
        ->assertSessionHasErrors(['phone', 'email', 'website']);

    expect($tenant->refresh()->name)->toBe('Completion Tenant');
});
