<?php

use App\Application\Payments\KashierTenantWebhookHandler;
use App\Application\Payments\TenantPaymentManager;
use App\Domain\Payments\TenantPaymentStatus;
use App\Domain\Payments\TenantPaymentRefundStatus;
use App\Domain\Tenancy\TenantContext;
use App\Infrastructure\Payments\Kashier\Exceptions\InvalidWebhookSignature;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\PaymentProviderAccount;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\TenantPaymentRefund;
use App\Models\TenantPaymentWebhookEvent;
use DomainException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
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

    if (isset($this->tenantFinancialDatabases)) {
        foreach ($this->tenantFinancialDatabases as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

function tenantFinancialDatabase(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/tenant-financial-'.Str::ulid().'.sqlite';

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

function migrateTenantFinancialDatabase(string $path): void
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

function tenantFinancialContext(Tenant $tenant, string $path): void
{
    $tenant->database_name = $path;

    app(TenantContext::class)->set($tenant);

    app(TenantDatabaseManager::class)->connect($tenant);
}

function createKashierAccount(Tenant $tenant, array $overrides = []): PaymentProviderAccount
{
    return PaymentProviderAccount::query()->create(array_replace([
        'tenant_id' => $tenant->getKey(),
        'provider' => 'kashier',
        'account_reference' => 'KASHIER-'.Str::upper(Str::random(8)),
        'status' => 'active',
        'encrypted_credentials' => [
            'merchant_id' => 'TENANT-MID',
            'secret_key' => 'TENANT-SECRET',
            'payment_api_key' => 'TENANT-API',
            'merchant_redirect_url' => 'https://tenant.example/return',
            'webhook_url' => 'https://tenant.example/webhooks/kashier',
        ],
    ], $overrides));
}

function tenantKashierSignature(array $data, string $apiKey): string
{
    $keys = $data['signatureKeys'];
    sort($keys, SORT_STRING);

    $parts = [];

    foreach ($keys as $key) {
        $parts[] = $key.'='.rawurlencode($data[$key] ?? '');
    }

    return hash_hmac('sha256', implode('&', $parts), $apiKey);
}

function tenantWebhookPayload(
    Tenant $tenant,
    string $status = 'SUCCESS',
    string $amount = '100.00',
    array $overrides = [],
): array {
    $data = array_replace([
        'amount' => $amount,
        'currency' => 'EGP',
        'orderId' => 'KASHIER-ORDER-1',
        'merchantOrderId' => $tenant->getKey().'.'.Str::ulid(),
        'status' => $status,
        'transactionId' => 'TX-'.Str::ulid(),
        'orderReference' => 'BOOKING-1',
        'signatureKeys' => [
            'transactionId',
            'status',
            'merchantOrderId',
            'orderReference',
            'orderId',
            'currency',
            'amount',
        ],
    ], $overrides);

    return ['event' => 'pay', 'data' => $data];
}

test('tenant webhook verifies against the tenant merchant api key and is idempotent', function (): void {
    $path = tenantFinancialDatabase();
    $this->tenantFinancialDatabases = [$path];
    migrateTenantFinancialDatabase($path);

    $tenant = Tenant::factory()->create([
        'database_name' => $path,
        'database_status' => 'ready',
        'status' => 'active',
    ]);

    createKashierAccount($tenant);

    tenantFinancialContext($tenant, $path);

    $merchantOrderId = $tenant->getKey().'.'.Str::ulid();

    $payment = TenantPayment::query()->create([
        'provider' => 'kashier',
        'merchant_order_id' => $merchantOrderId,
        'amount_minor' => 10000,
        'currency' => 'EGP',
        'status' => TenantPaymentStatus::Pending,
    ]);

    app(TenantContext::class)->clear();
    app(TenantDatabaseManager::class)->disconnect();

    $payload = tenantWebhookPayload($tenant, overrides: [
        'merchantOrderId' => $merchantOrderId,
        'transactionId' => 'TX-TENANT-1',
        'orderId' => 'KASHIER-ORDER-1',
    ]);

    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = tenantKashierSignature($payload['data'], 'TENANT-API');
    $handler = app(KashierTenantWebhookHandler::class);

    expect($handler->handle(
        $payload,
        ['X-Kashier-Signature' => $signature],
        $raw,
    ))->toBe(['duplicate' => false, 'status' => 'SUCCESS']);

    expect($handler->handle(
        $payload,
        ['X-Kashier-Signature' => $signature],
        $raw,
    ))->toBe(['duplicate' => true, 'status' => 'processed']);

    tenantFinancialContext($tenant, $path);

    $payment->refresh();

    expect($payment->status)->toBe(TenantPaymentStatus::Succeeded)
        ->and($payment->provider_payment_id)->toBe('TX-TENANT-1')
        ->and($payment->provider_order_id)->toBe('KASHIER-ORDER-1')
        ->and(TenantPaymentWebhookEvent::query()->count())->toBe(1)
        ->and(TenantPaymentWebhookEvent::query()->first()->status)->toBe('processed');
});

test('tenant webhook rejects invalid signatures before changing payment state', function (): void {
    $path = tenantFinancialDatabase();
    $this->tenantFinancialDatabases = [$path];
    migrateTenantFinancialDatabase($path);

    $tenant = Tenant::factory()->create([
        'database_name' => $path,
        'database_status' => 'ready',
        'status' => 'active',
    ]);

    createKashierAccount($tenant);

    tenantFinancialContext($tenant, $path);

    $merchantOrderId = $tenant->getKey().'.'.Str::ulid();

    TenantPayment::query()->create([
        'provider' => 'kashier',
        'merchant_order_id' => $merchantOrderId,
        'amount_minor' => 10000,
        'currency' => 'EGP',
        'status' => TenantPaymentStatus::Pending,
    ]);

    app(TenantContext::class)->clear();
    app(TenantDatabaseManager::class)->disconnect();

    $payload = tenantWebhookPayload($tenant, overrides: [
        'merchantOrderId' => $merchantOrderId,
    ]);

    expect(fn () => app(KashierTenantWebhookHandler::class)->handle(
        $payload,
        ['X-Kashier-Signature' => 'invalid'],
        json_encode($payload, JSON_THROW_ON_ERROR),
    ))->toThrow(InvalidWebhookSignature::class);

    tenantFinancialContext($tenant, $path);

    expect(TenantPayment::query()->value('status'))->toBe(TenantPaymentStatus::Pending->value)
        ->and(TenantPaymentWebhookEvent::query()->count())->toBe(0);
});

test('tenant webhook rejects amount or currency mismatches', function (): void {
    $path = tenantFinancialDatabase();
    $this->tenantFinancialDatabases = [$path];
    migrateTenantFinancialDatabase($path);

    $tenant = Tenant::factory()->create([
        'database_name' => $path,
        'database_status' => 'ready',
        'status' => 'active',
    ]);

    createKashierAccount($tenant);

    tenantFinancialContext($tenant, $path);

    $merchantOrderId = $tenant->getKey().'.'.Str::ulid();

    TenantPayment::query()->create([
        'provider' => 'kashier',
        'merchant_order_id' => $merchantOrderId,
        'amount_minor' => 10000,
        'currency' => 'EGP',
        'status' => TenantPaymentStatus::Pending,
    ]);

    app(TenantContext::class)->clear();
    app(TenantDatabaseManager::class)->disconnect();

    $payload = tenantWebhookPayload($tenant, overrides: [
        'merchantOrderId' => $merchantOrderId,
        'amount' => '99.00',
    ]);

    $signature = tenantKashierSignature($payload['data'], 'TENANT-API');

    expect(fn () => app(KashierTenantWebhookHandler::class)->handle(
        $payload,
        ['X-Kashier-Signature' => $signature],
        json_encode($payload, JSON_THROW_ON_ERROR),
    ))->toThrow(DomainException::class);
});

test('tenant webhook detects a different payload for the same event identifier', function (): void {
    $path = tenantFinancialDatabase();
    $this->tenantFinancialDatabases = [$path];
    migrateTenantFinancialDatabase($path);

    $tenant = Tenant::factory()->create([
        'database_name' => $path,
        'database_status' => 'ready',
        'status' => 'active',
    ]);

    createKashierAccount($tenant);

    tenantFinancialContext($tenant, $path);

    $merchantOrderId = $tenant->getKey().'.'.Str::ulid();

    TenantPayment::query()->create([
        'provider' => 'kashier',
        'merchant_order_id' => $merchantOrderId,
        'amount_minor' => 10000,
        'currency' => 'EGP',
        'status' => TenantPaymentStatus::Pending,
    ]);

    app(TenantContext::class)->clear();
    app(TenantDatabaseManager::class)->disconnect();

    $payload = tenantWebhookPayload($tenant, overrides: [
        'merchantOrderId' => $merchantOrderId,
        'transactionId' => 'TX-SAME',
        'orderId' => 'ORDER-SAME',
    ]);

    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = tenantKashierSignature($payload['data'], 'TENANT-API');
    $handler = app(KashierTenantWebhookHandler::class);

    $handler->handle(
        $payload,
        ['X-Kashier-Signature' => $signature],
        $raw,
    );

    $tampered = $payload;
    $tampered['extra'] = 'changed';

    expect(fn () => $handler->handle(
        $tampered,
        ['X-Kashier-Signature' => $signature],
        json_encode($tampered, JSON_THROW_ON_ERROR),
    ))->toThrow(DomainException::class);
});

test('tenant payment reconciliation verifies the provider result before marking paid', function (): void {
    $path = tenantFinancialDatabase();
    $this->tenantFinancialDatabases = [$path];
    migrateTenantFinancialDatabase($path);

    $tenant = Tenant::factory()->create([
        'database_name' => $path,
        'database_status' => 'ready',
        'status' => 'active',
    ]);

    $account = createKashierAccount($tenant);

    tenantFinancialContext($tenant, $path);

    $payment = TenantPayment::query()->create([
        'provider' => 'kashier',
        'merchant_order_id' => $tenant->getKey().'.'.Str::ulid(),
        'provider_session_id' => 'SESSION-RECON-1',
        'amount_minor' => 10000,
        'currency' => 'EGP',
        'status' => TenantPaymentStatus::Pending,
        'metadata' => [
            'payment_provider_account_id' => $account->getKey(),
        ],
    ]);

    Http::fake([
        'https://test-api.kashier.io/v3/payment/sessions/SESSION-RECON-1/payment' => Http::response([
            'status' => 'SUCCESS',
            'transactionId' => 'TX-RECON-1',
            'orderId' => 'KASHIER-RECON-ORDER',
            'amount' => '100.00',
            'currency' => 'EGP',
        ], 200),
    ]);

    $reconciled = app(TenantPaymentManager::class)->reconcile($payment);

    expect($reconciled->status)->toBe(TenantPaymentStatus::Succeeded)
        ->and($reconciled->provider_payment_id)->toBe('TX-RECON-1')
        ->and($reconciled->provider_order_id)->toBe('KASHIER-RECON-ORDER')
        ->and($payment->fresh()->status)->toBe(TenantPaymentStatus::Succeeded);
});

test('tenant refunds support idempotent partial and full refunds without platform records', function (): void {
    $path = tenantFinancialDatabase();
    $this->tenantFinancialDatabases = [$path];
    migrateTenantFinancialDatabase($path);

    $tenant = Tenant::factory()->create([
        'database_name' => $path,
        'database_status' => 'ready',
        'status' => 'active',
    ]);

    $account = createKashierAccount($tenant);

    tenantFinancialContext($tenant, $path);

    $payment = TenantPayment::query()->create([
        'provider' => 'kashier',
        'merchant_order_id' => $tenant->getKey().'.'.Str::ulid(),
        'provider_payment_id' => 'TX-PAID-1',
        'provider_order_id' => 'KASHIER-PAID-ORDER',
        'amount_minor' => 10000,
        'currency' => 'EGP',
        'status' => TenantPaymentStatus::Succeeded,
        'metadata' => [
            'payment_provider_account_id' => $account->getKey(),
        ],
    ]);

    Http::fake([
        'https://test-fep.kashier.io/v3/orders/KASHIER-PAID-ORDER' => Http::response([
            'response' => [
                'status' => 'SUCCESS',
                'transactionId' => 'REF-1',
            ],
        ], 200),
    ]);

    $manager = app(TenantPaymentManager::class);

    $first = $manager->refund(
        $payment,
        3000,
        'Customer requested partial refund',
        'refund-1',
    );

    $same = $manager->refund(
        $payment,
        3000,
        'Customer requested partial refund',
        'refund-1',
    );

    expect($first->status)->toBe(TenantPaymentRefundStatus::Succeeded)
        ->and($same->getKey())->toBe($first->getKey())
        ->and($payment->fresh()->status)->toBe(TenantPaymentStatus::Succeeded)
        ->and(TenantPaymentRefund::query()->count())->toBe(1);

    Http::assertSentCount(1);

    $second = $manager->refund(
        $payment,
        7000,
        'Customer requested full refund',
        'refund-2',
    );

    expect($second->status)->toBe(TenantPaymentRefundStatus::Succeeded)
        ->and($payment->fresh()->status)->toBe(TenantPaymentStatus::Refunded)
        ->and(TenantPaymentRefund::query()->sum('amount_minor'))->toBe(10000);

    expect(DB::connection('central')->table('platform_payments')->count())->toBe(0);
});


test('full tenant refund synchronizes an appointment to refunded', function (): void {
    $path = tenantFinancialDatabase();
    $this->tenantFinancialDatabases = [$path];
    migrateTenantFinancialDatabase($path);

    $tenant = Tenant::factory()->create([
        'database_name' => $path,
        'database_status' => 'ready',
        'status' => 'active',
    ]);

    $account = createKashierAccount($tenant);

    tenantFinancialContext($tenant, $path);

    $appointmentId = (string) Str::ulid();

    $payment = TenantPayment::query()->create([
        'appointment_id' => $appointmentId,
        'provider' => 'kashier',
        'merchant_order_id' => $tenant->getKey().'.'.Str::ulid(),
        'provider_payment_id' => 'TX-FULL-REFUND',
        'provider_order_id' => 'KASHIER-FULL-REFUND',
        'amount_minor' => 10000,
        'currency' => 'EGP',
        'status' => TenantPaymentStatus::Succeeded,
        'metadata' => [
            'payment_provider_account_id' => $account->getKey(),
        ],
    ]);

    // Use a real appointment row so the synchronization path is exercised.
    $appointmentId = DB::table('appointments')->insertGetId([
        'id' => $appointmentId,
        'customer_id' => null,
        'staff_id' => null,
        'location_id' => null,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'blocked_starts_at' => now()->addDay(),
        'blocked_ends_at' => now()->addDay()->addHour(),
        'status' => 'confirmed',
        'payment_status' => 'paid',
        'idempotency_key' => 'refund-appt-'.Str::ulid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payment->update(['appointment_id' => $appointmentId]);

    Http::fake([
        'https://test-fep.kashier.io/v3/orders/KASHIER-FULL-REFUND' => Http::response([
            'response' => [
                'status' => 'SUCCESS',
                'transactionId' => 'REF-FULL-1',
            ],
        ], 200),
    ]);

    $refund = app(TenantPaymentManager::class)->refund(
        $payment,
        10000,
        'Full refund',
        'refund-full',
    );

    expect($refund->status)->toBe(TenantPaymentRefundStatus::Succeeded)
        ->and($payment->fresh()->status)->toBe(TenantPaymentStatus::Refunded)
        ->and(DB::table('appointments')->where('id', $appointmentId)->value('payment_status'))
        ->toBe('refunded');
});

test('tenant refunds reject amounts above the reserved refundable balance', function (): void {
    $path = tenantFinancialDatabase();
    $this->tenantFinancialDatabases = [$path];
    migrateTenantFinancialDatabase($path);

    $tenant = Tenant::factory()->create([
        'database_name' => $path,
        'database_status' => 'ready',
        'status' => 'active',
    ]);

    $account = createKashierAccount($tenant);

    tenantFinancialContext($tenant, $path);

    $payment = TenantPayment::query()->create([
        'provider' => 'kashier',
        'merchant_order_id' => $tenant->getKey().'.'.Str::ulid(),
        'amount_minor' => 10000,
        'currency' => 'EGP',
        'status' => TenantPaymentStatus::Succeeded,
        'metadata' => [
            'payment_provider_account_id' => $account->getKey(),
        ],
    ]);

    TenantPaymentRefund::query()->create([
        'tenant_payment_id' => $payment->getKey(),
        'amount_minor' => 8000,
        'currency' => 'EGP',
        'status' => TenantPaymentRefundStatus::Pending,
        'requested_at' => now(),
    ]);

    expect(fn () => app(TenantPaymentManager::class)->refund(
        $payment,
        3000,
        'Too much',
    ))->toThrow(DomainException::class);
});
