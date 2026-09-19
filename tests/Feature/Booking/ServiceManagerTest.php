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
