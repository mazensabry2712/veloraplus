<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\QueueEntryStatus;
use App\Domain\Booking\QueueStatus;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\AppointmentStatusHistory;
use App\Models\Customer;
use App\Models\Location;
use App\Models\PlatformAccount;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\Service;
use App\Models\Staff;
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
    $this->dashboardTenantDatabasePaths = [];
});

function dashboardTestTenantDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/dashboard-'.Str::ulid().'.sqlite';
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

function migrateDashboardTenantDatabase(string $path): void
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
        DB::purge(TenantDatabaseManager::CONNECTION);
    }
}

function createDashboardTenant(string $path): Tenant
{
    $tenant = Tenant::factory()->create([
        'name' => 'Dashboard Tenant',
        'slug' => 'dashboard-tenant',
        'country_code' => 'EG',
        'default_currency' => 'EGP',
        'timezone' => 'Africa/Cairo',
        'database_name' => $path,
        'database_host' => null,
        'database_port' => null,
        'status' => 'active',
        'database_status' => 'ready',
    ]);

    TenantDomain::create([
        'tenant_id' => $tenant->getKey(),
        'domain' => 'dashboard-tenant.velora.test',
        'type' => 'subdomain',
        'is_primary' => true,
        'status' => 'active',
        'verified_at' => now(),
    ]);

    migrateDashboardTenantDatabase($path);

    return $tenant;
}

function dashboardSeedOperationalData(Tenant $tenant): void
{
    app(TenantDatabaseManager::class)->connect($tenant);

    try {
        $location = Location::factory()->create([
            'name' => 'Main Branch',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
        ]);
        $staff = Staff::factory()->forLocation($location)->create([
            'name' => 'Mazen Staff',
            'status' => 'active',
        ]);
        $service = Service::factory()->create([
            'name' => 'Consultation',
            'duration_minutes' => 60,
            'price_minor' => 25000,
            'currency' => 'EGP',
            'status' => 'active',
        ]);
        DB::table('staff_services')->insert([
            'id' => (string) Str::ulid(),
            'staff_id' => $staff->getKey(),
            'service_id' => $service->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customer = Customer::factory()->create([
            'name' => 'Dashboard Customer',
            'status' => 'active',
        ]);

        $startsAt = now()->setTimezone('Africa/Cairo')->addHours(2)->utc();
        $appointment = Appointment::factory()
            ->for($customer)
            ->for($staff)
            ->for($location)
            ->create([
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHour(),
                'blocked_starts_at' => $startsAt,
                'blocked_ends_at' => $startsAt->copy()->addHour(),
                'status' => AppointmentStatus::Confirmed,
            ]);

        AppointmentItem::query()->create([
            'appointment_id' => $appointment->getKey(),
            'service_id' => $service->getKey(),
            'service_name' => $service->name,
            'duration_minutes' => $service->duration_minutes,
            'quantity' => 1,
            'unit_price_minor' => $service->price_minor,
            'currency' => $service->currency,
            'line_total_minor' => $service->price_minor,
            'metadata' => [],
        ]);

        AppointmentStatusHistory::query()->create([
            'appointment_id' => $appointment->getKey(),
            'from_status' => null,
            'to_status' => AppointmentStatus::Confirmed->value,
            'reason' => 'Appointment created.',
            'changed_at' => now(),
            'metadata' => [],
        ]);

        $queue = Queue::factory()->for($location)->for($service)->create([
            'business_date' => now()->setTimezone('Africa/Cairo')->toDateString(),
            'status' => QueueStatus::Open,
            'next_position' => 3,
        ]);

        QueueEntry::factory()->for($queue)->for($customer)->create([
            'position' => 1,
            'status' => QueueEntryStatus::Waiting,
        ]);
        QueueEntry::factory()->for($queue)->for($customer)->create([
            'position' => 2,
            'status' => QueueEntryStatus::Serving,
        ]);
    } finally {
        app(TenantDatabaseManager::class)->disconnect();
    }
}

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');

    config([
        'database.connections.tenant_template' => $this->originalTenantTemplate,
    ]);

    foreach ($this->dashboardTenantDatabasePaths as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('guest is redirected from the company dashboard', function (): void {
    $path = dashboardTestTenantDatabasePath();
    $this->dashboardTenantDatabasePaths[] = $path;

    createDashboardTenant($path);

    $this->get('http://dashboard-tenant.velora.test/dashboard')
        ->assertRedirect('/login');
});

test('active tenant member can access the company dashboard', function (): void {
    $path = dashboardTestTenantDatabasePath();
    $this->dashboardTenantDatabasePaths[] = $path;

    $tenant = createDashboardTenant($path);
    $member = PlatformAccount::factory()->create([
        'name' => 'Dashboard Member',
    ]);

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $member->getKey(),
        'role_key' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    app(TenantRbacBootstrapper::class)->bootstrapForTenant($tenant);
    dashboardSeedOperationalData($tenant);

    $this->actingAs($member)
        ->get('http://dashboard-tenant.velora.test/dashboard')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false)
        ->assertSee('Overview')
        ->assertSee('Dashboard Tenant')
        ->assertSee('Powered by VeloraPlus')
        ->assertSee('<title>Overview · Dashboard Tenant</title>', false)
        ->assertDontSee('<title>Overview · VeloraPlus</title>', false)
        ->assertDontSee('alt="VeloraPlus"', false)
        ->assertDontSee('VeloraPlus dashboard', false)
        ->assertSee('Dashboard Member')
        ->assertSee('Owner')
        ->assertSee('Operational summary')
        ->assertSee('Upcoming appointments')
        ->assertSee("Today's queue")
        ->assertSee('Recent activity')
        ->assertSee('Dashboard Customer')
        ->assertSee('Consultation')
        ->assertSee('Main Branch');
});

test('tenant member can switch dashboard language to arabic', function (): void {
    $path = dashboardTestTenantDatabasePath();
    $this->dashboardTenantDatabasePaths[] = $path;

    $tenant = createDashboardTenant($path);
    $member = PlatformAccount::factory()->create([
        'name' => 'Dashboard Member',
    ]);

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $member->getKey(),
        'role_key' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    app(TenantRbacBootstrapper::class)->bootstrapForTenant($tenant);
    dashboardSeedOperationalData($tenant);

    $this->actingAs($member)
        ->post('http://dashboard-tenant.velora.test/dashboard/preferences/locale', [
            'locale' => 'ar',
        ])
        ->assertRedirect();

    $this->get('http://dashboard-tenant.velora.test/dashboard')
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('نظرة عامة', false)
        ->assertSee('ملخص التشغيل', false);
});

test('account without an active tenant membership cannot access the dashboard', function (): void {
    $path = dashboardTestTenantDatabasePath();
    $this->dashboardTenantDatabasePaths[] = $path;

    createDashboardTenant($path);
    $outsider = PlatformAccount::factory()->create();

    $this->actingAs($outsider)
        ->get('http://dashboard-tenant.velora.test/dashboard')
        ->assertForbidden();
});
