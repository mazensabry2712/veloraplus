<?php

use App\Application\Booking\ServiceManager;
use App\Domain\Booking\ServiceStatus;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

afterEach(function () {
    DB::purge(TenantDatabaseManager::CONNECTION);
    setPermissionsTeamId(null);

    if (isset($this->bookingTestDatabase) && is_file($this->bookingTestDatabase)) {
        unlink($this->bookingTestDatabase);
    }
});

function bookingTestDatabase(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/booking-'.Str::ulid().'.sqlite';

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

function migrateBookingTestDatabase(string $path): void
{
    config(['database.connections.tenant_template.database' => $path]);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        expect(Artisan::call('migrate', [
            '--database' => TenantDatabaseManager::CONNECTION,
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]))->toBe(0);
    } finally {
        $manager->disconnect();
    }
}

test('booking service migration creates the tenant service schema', function () {
    $path = bookingTestDatabase();

    try {
        migrateBookingTestDatabase($path);

        $tenant = new Tenant;
        $tenant->database_name = $path;

        $manager = app(TenantDatabaseManager::class);
        $manager->connect($tenant);

        expect(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('services'))->toBeTrue()
            ->and((new Service)->getConnectionName())->toBe(TenantDatabaseManager::CONNECTION)
            ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasColumn('services', 'slug'))->toBeTrue()
            ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasColumn('services', 'duration_minutes'))->toBeTrue()
            ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasColumn('services', 'price_minor'))->toBeTrue()
            ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasColumn('services', 'online_bookable'))->toBeTrue();

        $manager->disconnect();
    } finally {
        DB::purge(TenantDatabaseManager::CONNECTION);

        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('service factory creates valid tenant services', function () {
    $path = bookingTestDatabase();
    $this->bookingTestDatabase = $path;

    migrateBookingTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    $service = Service::factory()->create();

    expect($service->exists)->toBeTrue()
        ->and($service->status)->toBe(ServiceStatus::Active)
        ->and($service->slug)->not->toBeEmpty()
        ->and($service->currency)->toBe('EGP')
        ->and($service->duration_minutes)->toBeGreaterThan(0);

    $manager->disconnect();
});

test('service manager creates updates and archives a service', function () {
    $path = bookingTestDatabase();
    $this->bookingTestDatabase = $path;

    migrateBookingTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    $services = app(ServiceManager::class);

    $service = $services->create([
        'name' => 'Consultation',
        'description' => 'Initial consultation',
        'duration_minutes' => 60,
        'buffer_before_minutes' => 10,
        'buffer_after_minutes' => 5,
        'price_minor' => 50000,
        'currency' => 'egp',
        'deposit_amount_minor' => 10000,
        'online_bookable' => true,
        'capacity' => 1,
        'metadata' => ['category' => 'general'],
    ]);

    expect($service->status)->toBe(ServiceStatus::Active)
        ->and($service->slug)->toBe('consultation')
        ->and($service->currency)->toBe('EGP')
        ->and($service->duration_minutes)->toBe(60)
        ->and($service->price_minor)->toBe(50000);

    $updated = $services->update($service, [
        'name' => 'Extended Consultation',
        'duration_minutes' => 90,
        'price_minor' => 75000,
        'deposit_amount_minor' => 15000,
        'capacity' => 2,
    ]);

    expect($updated->name)->toBe('Extended Consultation')
        ->and($updated->duration_minutes)->toBe(90)
        ->and($updated->price_minor)->toBe(75000)
        ->and($updated->deposit_amount_minor)->toBe(15000)
        ->and($updated->capacity)->toBe(2);

    $archived = $services->archive($updated);

    expect($archived->status)->toBe(ServiceStatus::Inactive)
        ->and($archived->trashed())->toBeTrue()
        ->and(Service::query()->find($service->getKey()))->toBeNull()
        ->and(Service::withTrashed()->find($service->getKey()))->not->toBeNull();

    $manager->disconnect();
});


test('service manager generates unique stable slugs and preserves them on rename', function () {
    $path = bookingTestDatabase();
    $this->bookingTestDatabase = $path;

    migrateBookingTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    $services = app(ServiceManager::class);

    $first = $services->create([
        'name' => 'Dental Consultation',
        'duration_minutes' => 30,
        'price_minor' => 10000,
        'currency' => 'EGP',
        'deposit_amount_minor' => 0,
        'capacity' => 1,
    ]);

    $second = $services->create([
        'name' => 'Dental Consultation',
        'duration_minutes' => 45,
        'price_minor' => 15000,
        'currency' => 'EGP',
        'deposit_amount_minor' => 0,
        'capacity' => 1,
    ]);

    expect($first->slug)->toBe('dental-consultation')
        ->and($second->slug)->toBe('dental-consultation-2');

    $updated = $services->update($first, [
        'name' => 'Premium Dental Consultation',
    ]);

    expect($updated->name)->toBe('Premium Dental Consultation')
        ->and($updated->slug)->toBe('dental-consultation');

    $manager->disconnect();
});

test('service manager accepts and normalizes an explicit public slug', function () {
    $path = bookingTestDatabase();
    $this->bookingTestDatabase = $path;

    migrateBookingTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    $service = app(ServiceManager::class)->create([
        'name' => 'Skin Care',
        'slug' => ' Skin Care & Consultation ',
        'duration_minutes' => 60,
        'price_minor' => 25000,
        'currency' => 'EGP',
        'deposit_amount_minor' => 0,
        'capacity' => 1,
    ]);

    expect($service->slug)->toBe('skin-care-consultation');

    $manager->disconnect();
});


test('service manager stores SEO overrides and creates slug redirect history', function () {
    $path = bookingTestDatabase();
    $this->bookingTestDatabase = $path;

    migrateBookingTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    $services = app(ServiceManager::class);

    $service = $services->create([
        'name' => 'Dental Cleaning',
        'slug' => 'dental-cleaning',
        'seo_title' => 'Professional Dental Cleaning',
        'seo_description' => 'Dental cleaning service for routine oral care.',
        'social_image_url' => 'https://cdn.example.com/dental-cleaning.jpg',
        'duration_minutes' => 45,
        'price_minor' => 25000,
        'currency' => 'EGP',
        'deposit_amount_minor' => 0,
        'capacity' => 1,
    ]);

    expect($service->seo_title)->toBe('Professional Dental Cleaning')
        ->and($service->seo_description)->toBe('Dental cleaning service for routine oral care.')
        ->and($service->social_image_url)->toBe('https://cdn.example.com/dental-cleaning.jpg');

    $updated = $services->update($service, [
        'slug' => 'professional-dental-cleaning',
    ]);

    $redirect = \App\Models\ServiceSlugRedirect::query()
        ->where('old_slug', 'dental-cleaning')
        ->first();

    expect($updated->slug)->toBe('professional-dental-cleaning')
        ->and($redirect)->not->toBeNull()
        ->and($redirect?->service_id)->toBe($updated->getKey())
        ->and($redirect?->new_slug)->toBe('professional-dental-cleaning');

    $manager->disconnect();
});

test('service manager rejects reuse of a previous public service slug', function () {
    $path = bookingTestDatabase();
    $this->bookingTestDatabase = $path;

    migrateBookingTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    $services = app(ServiceManager::class);

    $first = $services->create([
        'name' => 'Dental Cleaning',
        'slug' => 'dental-cleaning',
        'duration_minutes' => 30,
        'price_minor' => 10000,
        'currency' => 'EGP',
        'deposit_amount_minor' => 0,
        'capacity' => 1,
    ]);

    $services->update($first, ['slug' => 'professional-dental-cleaning']);

    $second = $services->create([
        'name' => 'Another Service',
        'slug' => 'dental-cleaning',
        'duration_minutes' => 30,
        'price_minor' => 15000,
        'currency' => 'EGP',
        'deposit_amount_minor' => 0,
        'capacity' => 1,
    ]);

    expect($second->slug)->toBe('dental-cleaning-2');

    $manager->disconnect();
});

test('service manager rejects invalid money timing capacity status and currency', function () {
    $path = bookingTestDatabase();
    $this->bookingTestDatabase = $path;

    migrateBookingTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    $services = app(ServiceManager::class);

    expect(fn () => $services->create([
        'name' => 'Bad duration',
        'duration_minutes' => 0,
        'price_minor' => 1000,
        'currency' => 'EGP',
        'deposit_amount_minor' => 0,
        'capacity' => 1,
    ]))->toThrow(DomainException::class)
        ->and(fn () => $services->create([
            'name' => 'Bad price',
            'duration_minutes' => 30,
            'price_minor' => -1,
            'currency' => 'EGP',
            'deposit_amount_minor' => 0,
            'capacity' => 1,
        ]))->toThrow(DomainException::class)
        ->and(fn () => $services->create([
            'name' => 'Bad deposit',
            'duration_minutes' => 30,
            'price_minor' => 1000,
            'currency' => 'EGP',
            'deposit_amount_minor' => 1001,
            'capacity' => 1,
        ]))->toThrow(DomainException::class)
        ->and(fn () => $services->create([
            'name' => 'Bad capacity',
            'duration_minutes' => 30,
            'price_minor' => 1000,
            'currency' => 'EGP',
            'deposit_amount_minor' => 0,
            'capacity' => 0,
        ]))->toThrow(DomainException::class)
        ->and(fn () => $services->create([
            'name' => 'Bad currency',
            'duration_minutes' => 30,
            'price_minor' => 1000,
            'currency' => 'EG',
            'deposit_amount_minor' => 0,
            'capacity' => 1,
        ]))->toThrow(DomainException::class)
        ->and(fn () => $services->create([
            'name' => 'Bad status',
            'duration_minutes' => 30,
            'price_minor' => 1000,
            'currency' => 'EGP',
            'deposit_amount_minor' => 0,
            'capacity' => 1,
            'status' => 'archived',
        ]))->toThrow(DomainException::class);

    $manager->disconnect();
});
