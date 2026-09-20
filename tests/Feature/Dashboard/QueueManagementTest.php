<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Application\Booking\QueueManager;
use App\Application\Entitlements\EntitlementService;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Domain\Booking\QueueEntryStatus;
use App\Models\Customer;
use App\Models\Feature;
use App\Models\Location;
use App\Models\Module;
use App\Models\PlatformAccount;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\Service;
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
});

function queueDashboardDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/dashboard-queue-'.Str::ulid().'.sqlite';
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

function createQueueDashboardTenant(
    string $path,
    string $domain = 'queue-tenant.velora.test',
    string $slug = 'queue-tenant',
): Tenant {
    $tenant = Tenant::factory()->create([
        'name' => 'Queue Tenant',
        'slug' => $slug,
        'database_name' => $path,
        'database_host' => null,
        'database_port' => null,
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

    return $tenant;
}

function addQueueDashboardMember(Tenant $tenant, string $roleKey = 'owner'): PlatformAccount
{
    $account = PlatformAccount::factory()->create();

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $account->getKey(),
        'role_key' => $roleKey,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    app(TenantRbacBootstrapper::class)->bootstrapForTenant($tenant);

    return $account;
}

function enableQueueEntitlement(Tenant $tenant, ?Feature $feature = null): Feature
{
    if ($feature === null) {
        $module = Module::factory()->create([
            'key' => 'booking',
            'name' => 'Booking',
            'status' => 'active',
        ]);

        $feature = Feature::factory()->create([
            'module_id' => $module->getKey(),
            'key' => 'booking.queues',
            'name' => 'Queues',
            'status' => 'active',
        ]);
    }

    app(EntitlementService::class)->grant($tenant, $feature);

    return $feature;
}

function queueDashboardFixtures(Tenant $tenant): array
{
    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $location = Location::query()->create([
            'name' => 'Main Branch',
            'code' => 'QUEUE',
            'country_code' => 'EG',
            'city' => 'Cairo',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
            'metadata' => [],
        ]);

        $service = Service::query()->create([
            'name' => 'Walk-in',
            'duration_minutes' => 30,
            'price_minor' => 10000,
            'currency' => 'EGP',
            'deposit_amount_minor' => 0,
            'status' => 'active',
            'online_bookable' => false,
            'capacity' => 1,
            'metadata' => [],
        ]);

        $customer = Customer::query()->create([
            'name' => 'Customer One',
            'phone' => '+201000000000',
            'status' => 'active',
            'source' => 'dashboard',
            'metadata' => [],
        ]);

        return compact('location', 'service', 'customer');
    } finally {
        $manager->disconnect();
    }
}

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');
    app(\App\Domain\Tenancy\TenantContext::class)->clear();
    setPermissionsTeamId(null);
    config(['database.connections.tenant_template' => $this->originalTenantTemplate]);

    if (isset($this->queueDashboardDatabasePath) && is_file($this->queueDashboardDatabasePath)) {
        unlink($this->queueDashboardDatabasePath);
    }
});

test('owner can create and operate a queue through the dashboard backend', function (): void {
    $path = queueDashboardDatabasePath();
    $this->queueDashboardDatabasePath = $path;

    $tenant = createQueueDashboardTenant($path);
    $owner = addQueueDashboardMember($tenant);
    enableQueueEntitlement($tenant);
    $fixtures = queueDashboardFixtures($tenant);

    $this->actingAs($owner)
        ->post('http://queue-tenant.velora.test/dashboard/booking/queues', [
            'location_id' => $fixtures['location']->getKey(),
            'service_id' => $fixtures['service']->getKey(),
            'business_date' => '2026-09-21',
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Queue created successfully.');

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $queue = Queue::query()->firstOrFail();
    } finally {
        $manager->disconnect();
    }

    $this->actingAs($owner)
        ->post('http://queue-tenant.velora.test/dashboard/booking/queues/'.$queue->getKey().'/entries', [
            'customer_id' => $fixtures['customer']->getKey(),
            'idempotency_key' => 'queue-dashboard-entry',
        ])
        ->assertRedirect('/dashboard');

    $manager->connect($tenant);

    try {
        $entry = QueueEntry::query()->firstOrFail();
    } finally {
        $manager->disconnect();
    }

    $this->actingAs($owner)
        ->post('http://queue-tenant.velora.test/dashboard/booking/queues/'.$queue->getKey().'/call-next')
        ->assertRedirect('/dashboard');

    $this->actingAs($owner)
        ->post('http://queue-tenant.velora.test/dashboard/booking/queues/'.$queue->getKey().'/entries/'.$entry->getKey().'/complete')
        ->assertRedirect('/dashboard');

    $manager->connect($tenant);

    try {
        expect($entry->refresh()->status)->toBe(QueueEntryStatus::Completed);
    } finally {
        $manager->disconnect();
    }

    $this->actingAs($owner)
        ->post('http://queue-tenant.velora.test/dashboard/booking/queues/'.$queue->getKey().'/close')
        ->assertRedirect('/dashboard');

    $this->actingAs($owner)
        ->post('http://queue-tenant.velora.test/dashboard/booking/queues/'.$queue->getKey().'/open')
        ->assertRedirect('/dashboard');
});

test('viewer cannot manage queues and entitlement is required', function (): void {
    $path = queueDashboardDatabasePath();
    $this->queueDashboardDatabasePath = $path;

    $tenant = createQueueDashboardTenant($path);
    $viewer = addQueueDashboardMember($tenant, 'viewer');
    $fixtures = queueDashboardFixtures($tenant);

    $this->actingAs($viewer)
        ->post('http://queue-tenant.velora.test/dashboard/booking/queues', [
            'location_id' => $fixtures['location']->getKey(),
            'service_id' => $fixtures['service']->getKey(),
            'business_date' => '2026-09-21',
        ])
        ->assertForbidden();

    $owner = addQueueDashboardMember($tenant, 'owner');
    enableQueueEntitlement($tenant);

    $this->actingAs($owner)
        ->post('http://queue-tenant.velora.test/dashboard/booking/queues', [
            'location_id' => $fixtures['location']->getKey(),
            'service_id' => $fixtures['service']->getKey(),
            'business_date' => '2026-09-21',
        ])
        ->assertRedirect('/dashboard');
});

test('queue creation validation rejects foreign and invalid resources', function (): void {
    $pathA = queueDashboardDatabasePath();
    $pathB = queueDashboardDatabasePath();
    $this->queueDashboardDatabasePath = $pathA;

    $tenantA = createQueueDashboardTenant($pathA, 'queue-a.velora.test', 'queue-a');
    $tenantB = createQueueDashboardTenant($pathB, 'queue-b.velora.test', 'queue-b');
    $ownerA = addQueueDashboardMember($tenantA);
    $fixturesB = queueDashboardFixtures($tenantB);
    $queueFeature = enableQueueEntitlement($tenantA);
    enableQueueEntitlement($tenantB, $queueFeature);

    $this->actingAs($ownerA)
        ->post('http://queue-a.velora.test/dashboard/booking/queues', [
            'location_id' => $fixturesB['location']->getKey(),
            'service_id' => $fixturesB['service']->getKey(),
            'business_date' => 'bad-date',
        ])
        ->assertSessionHasErrors(['location_id', 'service_id', 'business_date']);

    DB::purge(TenantDatabaseManager::CONNECTION);
    if (is_file($pathB)) {
        unlink($pathB);
    }
});

test('queue entry reason actions enforce entry-to-queue ownership', function (): void {
    $path = queueDashboardDatabasePath();
    $this->queueDashboardDatabasePath = $path;

    $tenant = createQueueDashboardTenant($path);
    $owner = addQueueDashboardMember($tenant);
    enableQueueEntitlement($tenant);
    $fixtures = queueDashboardFixtures($tenant);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);
    try {
        $queueA = app(QueueManager::class)->createQueue($fixtures['location'], $fixtures['service'], '2026-09-21');
        $queueB = app(QueueManager::class)->createQueue($fixtures['location'], $fixtures['service'], '2026-09-22');
        $entryB = app(QueueManager::class)->enqueue($queueB, $fixtures['customer']);
    } finally {
        $manager->disconnect();
    }

    $this->actingAs($owner)
        ->post('http://queue-tenant.velora.test/dashboard/booking/queues/'.$queueA->getKey().'/entries/'.$entryB->getKey().'/skip', [
            'reason' => 'Wrong queue',
        ])
        ->assertNotFound();
});

test('owner can open the Booking Queue dashboard and see queue entries and controls', function (): void {
    $path = queueDashboardDatabasePath();
    $this->queueDashboardDatabasePath = $path;

    $tenant = createQueueDashboardTenant($path);
    $owner = addQueueDashboardMember($tenant);
    enableQueueEntitlement($tenant);
    $fixtures = queueDashboardFixtures($tenant);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $queue = app(QueueManager::class)->createQueue(
            $fixtures['location'],
            $fixtures['service'],
            '2026-09-21',
        );
        app(QueueManager::class)->enqueue($queue, $fixtures['customer']);
    } finally {
        $manager->disconnect();
    }

    $this->actingAs($owner)
        ->get('http://queue-tenant.velora.test/dashboard/booking/queues?date=2026-09-21')
        ->assertOk()
        ->assertSee('Queue', false)
        ->assertSee('Walk-in', false)
        ->assertSee('Main Branch', false)
        ->assertSee('Customer One', false)
        ->assertSee('Waiting', false)
        ->assertSee('Call Next', false)
        ->assertSee('Close Queue', false)
        ->assertSee('Add Customer', false)
        ->assertSee('Create Queue', false);
});

test('viewer can read Booking Queue but cannot see management controls', function (): void {
    $path = queueDashboardDatabasePath();
    $this->queueDashboardDatabasePath = $path;

    $tenant = createQueueDashboardTenant($path);
    $viewer = addQueueDashboardMember($tenant, 'viewer');
    enableQueueEntitlement($tenant);
    $fixtures = queueDashboardFixtures($tenant);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $queue = app(QueueManager::class)->createQueue(
            $fixtures['location'],
            $fixtures['service'],
            '2026-09-21',
        );
        app(QueueManager::class)->enqueue($queue, $fixtures['customer']);
    } finally {
        $manager->disconnect();
    }

    $this->actingAs($viewer)
        ->get('http://queue-tenant.velora.test/dashboard/booking/queues?date=2026-09-21')
        ->assertOk()
        ->assertSee('Customer One', false)
        ->assertSee('Waiting', false)
        ->assertDontSee('Create Queue', false)
        ->assertDontSee('Call Next', false)
        ->assertDontSee('Close Queue', false)
        ->assertDontSee('Add Customer', false);
});

test('Booking Queue dashboard requires the feature entitlement', function (): void {
    $path = queueDashboardDatabasePath();
    $this->queueDashboardDatabasePath = $path;

    $tenant = createQueueDashboardTenant($path);
    $owner = addQueueDashboardMember($tenant);

    $this->actingAs($owner)
        ->get('http://queue-tenant.velora.test/dashboard/booking/queues')
        ->assertForbidden();
});

