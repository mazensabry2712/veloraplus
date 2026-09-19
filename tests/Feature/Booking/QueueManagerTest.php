<?php

use App\Application\Booking\QueueManager;
use App\Domain\Booking\QueueEntryStatus;
use App\Domain\Booking\QueueStatus;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\Service;
use App\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->originalTenantTemplate = config('database.connections.tenant_template');

    DB::setDefaultConnection('central');

    expect(Artisan::call('migrate:fresh', [
        '--database' => 'central',
        '--force' => true,
    ]))->toBe(0);
});

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');
    app(TenantContext::class)->clear();
    config(['database.connections.tenant_template' => $this->originalTenantTemplate]);

    if (isset($this->queueTestDatabases)) {
        foreach ($this->queueTestDatabases as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

function queueTestDatabase(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/queue-'.Str::ulid().'.sqlite';

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

function migrateQueueTestDatabase(string $path): void
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

function queueTenantContext(Tenant $tenant, string $path): void
{
    $tenant->database_name = $path;

    app(TenantContext::class)->set($tenant);

    app(TenantDatabaseManager::class)->connect($tenant);
}

function queueFixtures(): array
{
    $location = Location::factory()->create([
        'name' => 'Main Branch',
        'timezone' => 'Africa/Cairo',
        'status' => 'active',
    ]);

    $service = Service::factory()->create([
        'name' => 'Consultation',
        'status' => 'active',
        'online_bookable' => true,
        'price_minor' => 10000,
        'currency' => 'EGP',
    ]);

    return compact('location', 'service');
}

function queueCustomer(string $name): Customer
{
    return Customer::factory()->create([
        'name' => $name,
        'status' => 'active',
    ]);
}

test('queue migrations create daily queue and entry tables', function (): void {
    $path = queueTestDatabase();
    $this->queueTestDatabases = [$path];

    migrateQueueTestDatabase($path);

    expect(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('queues'))->toBeTrue()
        ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('queue_entries'))->toBeTrue()
        ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasColumn('queues', 'next_position'))->toBeTrue()
        ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasColumn('queue_entries', 'position'))->toBeTrue();
});

test('queue manager creates one queue per location service and business date', function (): void {
    $path = queueTestDatabase();
    $this->queueTestDatabases = [$path];

    migrateQueueTestDatabase($path);

    $tenant = Tenant::factory()->create();
    queueTenantContext($tenant, $path);

    extract(queueFixtures());

    $manager = app(QueueManager::class);

    $first = $manager->createQueue($location, $service, '2026-09-21');
    $same = $manager->createQueue($location, $service, '2026-09-21');
    $nextDay = $manager->createQueue($location, $service, '2026-09-22');

    expect($same->getKey())->toBe($first->getKey())
        ->and($nextDay->getKey())->not->toBe($first->getKey())
        ->and(Queue::query()->count())->toBe(2);
});

test('queue positions are allocated atomically from the queue sequence and retry is idempotent', function (): void {
    $path = queueTestDatabase();
    $this->queueTestDatabases = [$path];

    migrateQueueTestDatabase($path);

    $tenant = Tenant::factory()->create();
    queueTenantContext($tenant, $path);

    extract(queueFixtures());

    $manager = app(QueueManager::class);
    $queue = $manager->createQueue($location, $service, '2026-09-21');

    $customerA = queueCustomer('Customer A');
    $customerB = queueCustomer('Customer B');

    $first = $manager->enqueue(
        queue: $queue,
        customer: $customerA,
        idempotencyKey: 'queue-entry-1',
    );

    $same = $manager->enqueue(
        queue: $queue,
        customer: $customerA,
        idempotencyKey: 'queue-entry-1',
    );

    $second = $manager->enqueue(
        queue: $queue,
        customer: $customerB,
        idempotencyKey: 'queue-entry-2',
    );

    expect($first->getKey())->toBe($same->getKey())
        ->and($first->position)->toBe(1)
        ->and($second->position)->toBe(2)
        ->and($queue->fresh()->next_position)->toBe(3)
        ->and($queue->entries()->count())->toBe(2);
});

test('queue rejects inactive customers and duplicate idempotency keys across queues', function (): void {
    $path = queueTestDatabase();
    $this->queueTestDatabases = [$path];

    migrateQueueTestDatabase($path);

    $tenant = Tenant::factory()->create();
    queueTenantContext($tenant, $path);

    extract(queueFixtures());
    $otherLocation = Location::factory()->create(['status' => 'active', 'timezone' => 'Africa/Cairo']);
    $otherQueue = app(QueueManager::class)->createQueue($otherLocation, $service, '2026-09-21');

    $inactive = queueCustomer('Inactive');
    $inactive->update(['status' => 'inactive']);

    $manager = app(QueueManager::class);
    $queue = $manager->createQueue($location, $service, '2026-09-21');

    expect(fn () => $manager->enqueue($queue, $inactive))
        ->toThrow(DomainException::class);

    $customer = queueCustomer('Customer');

    $manager->enqueue($queue, $customer, idempotencyKey: 'shared-key');

    expect(fn () => $manager->enqueue($otherQueue, $customer, idempotencyKey: 'shared-key'))
        ->toThrow(DomainException::class);
});

test('queue only allows one serving entry and lifecycle transitions are guarded', function (): void {
    $path = queueTestDatabase();
    $this->queueTestDatabases = [$path];

    migrateQueueTestDatabase($path);

    $tenant = Tenant::factory()->create();
    queueTenantContext($tenant, $path);

    extract(queueFixtures());

    $manager = app(QueueManager::class);
    $queue = $manager->createQueue($location, $service, '2026-09-21');

    $first = $manager->enqueue($queue, queueCustomer('First'));
    $second = $manager->enqueue($queue, queueCustomer('Second'));
    $third = $manager->enqueue($queue, queueCustomer('Third'));

    $serving = $manager->callNext($queue);

    expect($serving?->getKey())->toBe($first->getKey())
        ->and($serving?->status)->toBe(QueueEntryStatus::Serving);

    expect(fn () => $manager->callNext($queue))
        ->toThrow(DomainException::class);

    $completed = $manager->complete($serving);

    expect($completed->status)->toBe(QueueEntryStatus::Completed)
        ->and($completed->completed_at)->not->toBeNull();

    $next = $manager->callNext($queue);

    expect($next?->getKey())->toBe($second->getKey());

    $skipped = $manager->skip($next, 'Customer left');

    expect($skipped->status)->toBe(QueueEntryStatus::Skipped)
        ->and($skipped->skipped_at)->not->toBeNull()
        ->and($skipped->metadata['skip_reason'])->toBe('Customer left');

    $last = $manager->callNext($queue);
    $noShow = $manager->markNoShow($last, 'Customer did not respond');

    expect($noShow->status)->toBe(QueueEntryStatus::NoShow)
        ->and($noShow->no_show_at)->not->toBeNull();

    expect($manager->callNext($queue))->toBeNull();
});

test('closed queues reject new entries and can be reopened', function (): void {
    $path = queueTestDatabase();
    $this->queueTestDatabases = [$path];

    migrateQueueTestDatabase($path);

    $tenant = Tenant::factory()->create();
    queueTenantContext($tenant, $path);

    extract(queueFixtures());

    $manager = app(QueueManager::class);
    $queue = $manager->createQueue($location, $service, '2026-09-21');

    $closed = $manager->close($queue);

    expect($closed->status)->toBe(QueueStatus::Closed);

    expect(fn () => $manager->enqueue($closed, queueCustomer('Blocked')))
        ->toThrow(DomainException::class);

    $open = $manager->open($closed);

    expect($open->status)->toBe(QueueStatus::Open)
        ->and($manager->enqueue($open, queueCustomer('Allowed'))->position)->toBe(1);
});

test('queue appointment must match customer service location and business date', function (): void {
    $path = queueTestDatabase();
    $this->queueTestDatabases = [$path];

    migrateQueueTestDatabase($path);

    $tenant = Tenant::factory()->create();
    queueTenantContext($tenant, $path);

    extract(queueFixtures());

    $appointment = Appointment::factory()->create([
        'location_id' => $location->getKey(),
        'payment_status' => 'unpaid',
        'status' => 'confirmed',
    ]);

    DB::table('appointment_items')->insert([
        'id' => (string) Str::ulid(),
        'appointment_id' => $appointment->getKey(),
        'service_id' => $service->getKey(),
        'service_name' => $service->name,
        'duration_minutes' => $service->duration_minutes,
        'quantity' => 1,
        'unit_price_minor' => $service->price_minor,
        'currency' => $service->currency,
        'line_total_minor' => $service->price_minor,
        'metadata' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $queue = app(QueueManager::class)->createQueue(
        $location,
        $service,
        $appointment->starts_at->timezone('Africa/Cairo')->toDateString(),
    );

    $entry = app(QueueManager::class)->enqueue(
        queue: $queue,
        customer: Customer::query()->findOrFail($appointment->customer_id),
        appointment: $appointment,
    );

    expect($entry->appointment_id)->toBe($appointment->getKey());

    $otherService = Service::factory()->create(['status' => 'active']);
    $otherQueue = app(QueueManager::class)->createQueue($location, $otherService, $queue->business_date);

    expect(fn () => app(QueueManager::class)->enqueue(
        $otherQueue,
        Customer::query()->findOrFail($appointment->customer_id),
        appointment: $appointment,
    ))->toThrow(DomainException::class);
});

test('queue records remain isolated between tenant databases', function (): void {
    $pathA = queueTestDatabase();
    $pathB = queueTestDatabase();
    $this->queueTestDatabases = [$pathA, $pathB];

    migrateQueueTestDatabase($pathA);
    migrateQueueTestDatabase($pathB);

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    queueTenantContext($tenantA, $pathA);
    extract(queueFixtures());

    $queueA = app(QueueManager::class)->createQueue($location, $service, '2026-09-21');

    app(TenantDatabaseManager::class)->disconnect();
    app(TenantContext::class)->clear();

    queueTenantContext($tenantB, $pathB);

    expect(Queue::query()->whereKey($queueA->getKey())->exists())->toBeFalse();
});
