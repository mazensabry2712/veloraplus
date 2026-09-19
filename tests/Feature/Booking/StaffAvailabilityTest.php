<?php

use App\Application\Booking\StaffAvailabilityManager;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\StaffWorkingHour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

uses(RefreshDatabase::class);

afterEach(function () {
    DB::purge(TenantDatabaseManager::CONNECTION);
    setPermissionsTeamId(null);

    if (isset($this->availabilityTestDatabases)) {
        foreach ($this->availabilityTestDatabases as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

function availabilityTestDatabase(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/availability-'.Str::ulid().'.sqlite';

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

function migrateAvailabilityTestDatabase(string $path): void
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

test('availability migrations create the tenant scheduling schema', function () {
    $path = availabilityTestDatabase();
    $this->availabilityTestDatabases = [$path];

    migrateAvailabilityTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    expect(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('staff_working_hours'))->toBeTrue()
        ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('staff_breaks'))->toBeTrue()
        ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('staff_time_off'))->toBeTrue()
        ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('staff_services'))->toBeTrue();

    $manager->disconnect();
});

test('staff availability manager assigns services and manages non-overlapping schedules', function () {
    $path = availabilityTestDatabase();
    $this->availabilityTestDatabases = [$path];

    migrateAvailabilityTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    $location = Location::factory()->create([
        'timezone' => 'Africa/Cairo',
    ]);
    $staff = Staff::factory()->forLocation($location)->create();
    $service = Service::factory()->create();

    $availability = app(StaffAvailabilityManager::class);

    $assignment = $availability->assignService($staff, $service);

    expect($assignment->exists)->toBeTrue()
        ->and($staff->services()->whereKey($service->getKey())->exists())->toBeTrue()
        ->and($availability->assignService($staff, $service)->getKey())->toBe($assignment->getKey());

    $workingHour = $availability->saveWorkingHour($staff, 0, '09:00', '17:00');

    expect($workingHour->starts_at)->toBe('09:00:00')
        ->and($workingHour->ends_at)->toBe('17:00:00');

    $break = $availability->saveBreak($workingHour, '13:00', '14:00', 'Lunch');

    expect($break->label)->toBe('Lunch');

    expect(fn () => $availability->saveWorkingHour($staff, 0, '16:00', '18:00'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $availability->saveBreak($workingHour, '12:00', '13:00'))
        ->not->toThrow(InvalidArgumentException::class);

    $overlapBreak = fn () => $availability->saveBreak($workingHour, '13:15', '13:45');

    expect($overlapBreak)->toThrow(InvalidArgumentException::class);

    expect(fn () => $availability->saveBreak($workingHour, '17:00', '17:30'))
        ->toThrow(InvalidArgumentException::class);

    $availability->saveTimeOff(
        $staff,
        '2026-09-20 15:00:00+03:00',
        '2026-09-20 16:00:00+03:00',
        'Personal',
    );

    expect(fn () => $availability->saveTimeOff(
        $staff,
        '2026-09-20 15:30:00+03:00',
        '2026-09-20 16:30:00+03:00',
    ))->toThrow(InvalidArgumentException::class);

    $windows = $availability->availabilityForDate(
        $staff,
        CarbonImmutable::parse('2026-09-20', 'Africa/Cairo'),
    );

    expect($windows)->toHaveCount(3)
        ->and($windows[0]['starts_at']->format('H:i'))->toBe('09:00')
        ->and($windows[0]['ends_at']->format('H:i'))->toBe('12:30')
        ->and($windows[1]['starts_at']->format('H:i'))->toBe('14:00')
        ->and($windows[1]['ends_at']->format('H:i'))->toBe('15:00')
        ->and($windows[2]['starts_at']->format('H:i'))->toBe('16:00')
        ->and($windows[2]['ends_at']->format('H:i'))->toBe('17:00');

    $availability->unassignService($staff, $service);
    expect($staff->services()->whereKey($service->getKey())->exists())->toBeFalse();

    $manager->disconnect();
});

test('availability rules reject invalid ranges and archived records', function () {
    $path = availabilityTestDatabase();
    $this->availabilityTestDatabases = [$path];

    migrateAvailabilityTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    $location = Location::factory()->create();
    $staff = Staff::factory()->forLocation($location)->create();
    $service = Service::factory()->create();

    $availability = app(StaffAvailabilityManager::class);

    expect(fn () => $availability->saveWorkingHour($staff, 7, '09:00', '10:00'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $availability->saveWorkingHour($staff, 1, '10:00', '09:00'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $availability->saveWorkingHour($staff, 1, '9:00', '10:00'))
        ->toThrow(InvalidArgumentException::class);

    $service->delete();

    expect(fn () => $availability->assignService($staff, $service))
        ->toThrow(InvalidArgumentException::class);

    $manager->disconnect();
});

test('availability data remains isolated between tenant databases', function () {
    $pathA = availabilityTestDatabase();
    $pathB = availabilityTestDatabase();
    $this->availabilityTestDatabases = [$pathA, $pathB];

    migrateAvailabilityTestDatabase($pathA);
    migrateAvailabilityTestDatabase($pathB);

    $manager = app(TenantDatabaseManager::class);

    $tenantA = new Tenant;
    $tenantA->database_name = $pathA;

    $manager->connect($tenantA);

    $locationA = Location::factory()->create(['timezone' => 'Africa/Cairo']);
    $staffA = Staff::factory()->forLocation($locationA)->create();
    app(StaffAvailabilityManager::class)->saveWorkingHour($staffA, 1, '09:00', '12:00');

    $manager->disconnect();

    $tenantB = new Tenant;
    $tenantB->database_name = $pathB;

    $manager->connect($tenantB);

    $locationB = Location::factory()->create(['timezone' => 'Africa/Cairo']);
    $staffB = Staff::factory()->forLocation($locationB)->create();
    app(StaffAvailabilityManager::class)->saveWorkingHour($staffB, 1, '13:00', '17:00');

    $manager->disconnect();

    $manager->connect($tenantA);

    expect(Staff::query()->whereKey($staffA->getKey())->exists())->toBeTrue()
        ->and(StaffWorkingHour::query()->where('staff_id', $staffB->getKey())->exists())->toBeFalse();

    $manager->disconnect();
});
