<?php

use App\Application\Billing\PaymentService;
use App\Application\Payments\KashierWebhookHandler;
use App\Application\Payments\PaymentGatewayManager;
use App\Application\Payments\PlatformPaymentCheckoutService;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\PaymentStatus;
use App\Infrastructure\Payments\Kashier\KashierGateway;
use App\Infrastructure\Payments\Kashier\KashierWebhookVerifier;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('velora.payments.platform_provider', 'kashier');
    Config::set('velora.payments.tenant_provider', 'kashier');
    Config::set('velora.payments.kashier.mode', 'test');
    Config::set('velora.payments.kashier.secret_key', 'test-secret');
    Config::set('velora.payments.kashier.payment_api_key', '11111');
    Config::set('velora.payments.kashier.merchant_id', 'MID-TEST');
    Config::set('velora.payments.kashier.merchant_redirect_url', 'https://example.com/return');
    Config::set('velora.payments.kashier.webhook_url', 'https://example.com/webhooks/kashier/platform');
});

function makePlatformPayment(int $amountMinor = 10000): PlatformPayment
{
    $tenant = Tenant::factory()->create();

    $invoice = PlatformInvoice::query()->create([
        'tenant_id' => $tenant->getKey(),
        'subscription_id' => null,
        'number' => 'TEST-'.str()->upper(str()->random(12)),
        'status' => InvoiceStatus::Open,
        'currency' => 'EGP',
        'subtotal_minor' => $amountMinor,
        'discount_minor' => 0,
        'tax_minor' => 0,
        'total_minor' => $amountMinor,
        'issued_at' => now(),
    ]);

    return app(PaymentService::class)->createPending($invoice);
}

function kashierSignature(array $data): string
{
    $keys = $data['signatureKeys'];
    sort($keys, SORT_STRING);

    $parts = [];

    foreach ($keys as $key) {
        $parts[] = $key.'='.rawurlencode((string) ($data[$key] ?? ''));
    }

    return hash_hmac('sha256', implode('&', $parts), '11111');
}

test('payment gateway manager resolves platform and tenant providers', function () {
    $manager = app(PaymentGatewayManager::class);

    expect($manager->platform()->provider())->toBe('kashier')
        ->and($manager->tenant()->provider())->toBe('kashier');
});

test('kashier creates a hosted payment session through the adapter', function () {
    Http::fake([
        'https://test-api.kashier.io/v3/payment/sessions' => Http::response([
            'status' => 'CREATED',
            '_id' => 'SESSION-123',
            'sessionUrl' => 'https://payments.kashier.io/session/SESSION-123?mode=test',
        ], 200),
    ]);

    $payment = makePlatformPayment();
    $result = app(PlatformPaymentCheckoutService::class)->create($payment, [
        'email' => 'customer@example.com',
    ]);

    expect($result['provider'])->toBe('kashier')
        ->and($result['session_id'])->toBe('SESSION-123')
        ->and($result['checkout_url'])->toContain('payments.kashier.io');

    $payment->refresh();

    expect($payment->provider)->toBe('kashier')
        ->and($payment->metadata['checkout']['session_id'])->toBe('SESSION-123')
        ->and($payment->metadata['checkout']['checkout_url'])->toContain('payments.kashier.io');

    Http::assertSent(function ($request) use ($payment) {
        $body = $request->data();

        return $request->url() === 'https://test-api.kashier.io/v3/payment/sessions'
            && $request->method() === 'POST'
            && $body['amount'] === '100.00'
            && $body['currency'] === 'EGP'
            && $body['order'] === $payment->getKey()
            && $body['merchantId'] === 'MID-TEST';
    });
});


test('kashier tenant checkout uses the selected merchant account credentials', function () {
    Config::set('velora.payments.kashier.merchant_id', 'GLOBAL-MID');
    Config::set('velora.payments.kashier.secret_key', 'GLOBAL-SECRET');
    Config::set('velora.payments.kashier.payment_api_key', 'GLOBAL-API');

    Http::fake([
        'https://test-api.kashier.io/v3/payment/sessions' => Http::response([
            'status' => 'CREATED',
            '_id' => 'TENANT-SESSION-1',
            'sessionUrl' => 'https://payments.kashier.io/session/TENANT-SESSION-1',
        ], 200),
    ]);

    $result = app(KashierGateway::class)->createCheckout([
        'amount_minor' => 12500,
        'currency' => 'EGP',
        'merchant_order_id' => 'TENANT-ORDER-1',
        'payment_account' => [
            'reference' => 'CLINIC-ACCOUNT',
            'credentials' => [
                'merchant_id' => 'TENANT-MID',
                'secret_key' => 'TENANT-SECRET',
                'payment_api_key' => 'TENANT-API',
                'merchant_redirect_url' => 'https://clinic.example/return',
                'webhook_url' => 'https://clinic.example/webhooks/kashier',
            ],
        ],
    ]);

    expect($result['session_id'])->toBe('TENANT-SESSION-1');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->header('Authorization') === ['TENANT-SECRET']
            && $request->header('api-key') === ['TENANT-API']
            && $body['merchantId'] === 'TENANT-MID'
            && $body['merchantRedirect'] === 'https://clinic.example/return'
            && $body['serverWebhook'] === 'https://clinic.example/webhooks/kashier';
    });
});


test('kashier tenant checkout rejects incomplete tenant credentials instead of using platform credentials', function () {
    Config::set('velora.payments.kashier.merchant_id', 'GLOBAL-MID');
    Config::set('velora.payments.kashier.secret_key', 'GLOBAL-SECRET');
    Config::set('velora.payments.kashier.payment_api_key', 'GLOBAL-API');

    expect(fn () => app(KashierGateway::class)->createCheckout([
        'amount_minor' => 12500,
        'currency' => 'EGP',
        'merchant_order_id' => 'TENANT-ORDER-INVALID',
        'payment_account' => [
            'reference' => 'BROKEN-ACCOUNT',
            'credentials' => [],
        ],
    ]))->toThrow(DomainException::class);
});

test('kashier webhook signature verification follows the sorted signatureKeys rule', function () {
    $data = [
        'amount' => '1',
        'channel' => 'online | e-commerce',
        'currency' => 'EGP',
        'kashierOrderId' => '9ad06b17-755b-4e21-9774-aff3e2726ac9',
        'merchantOrderId' => '1653481557813',
        'method' => 'card',
        'orderReference' => 'TEST-ORD-38855',
        'status' => 'SUCCESS',
        'transactionId' => 'TX-249893963',
        'signatureKeys' => [
            'transactionId',
            'status',
            'merchantOrderId',
            'orderReference',
            'method',
            'kashierOrderId',
            'currency',
            'channel',
            'amount',
        ],
    ];

    app(KashierWebhookVerifier::class)->verify($data, kashierSignature($data));

    expect(true)->toBeTrue();
});

test('verified successful webhook marks platform payment paid exactly once', function () {
    $payment = makePlatformPayment();
    $payment->update(['provider' => 'kashier']);

    $data = [
        'amount' => '100.00',
        'currency' => 'EGP',
        'kashierOrderId' => 'KASHIER-ORDER-1',
        'merchantOrderId' => $payment->getKey(),
        'orderReference' => 'TEST-ORDER-1',
        'status' => 'SUCCESS',
        'transactionId' => 'TX-TEST-1',
        'signatureKeys' => [
            'transactionId',
            'status',
            'merchantOrderId',
            'orderReference',
            'kashierOrderId',
            'currency',
            'amount',
        ],
        'creationDate' => '2026-09-19T08:00:00+00:00',
    ];

    $payload = ['event' => 'pay', 'data' => $data];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $headers = ['X-Kashier-Signature' => kashierSignature($data)];

    $first = app(KashierWebhookHandler::class)->handle($payload, $headers, $raw);

    expect($first['duplicate'])->toBeFalse();

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->provider_payment_id)->toBe('TX-TEST-1')
        ->and($payment->provider_event_id)->not->toBeNull()
        ->and(WebhookEvent::query()->where('provider', 'kashier')->count())->toBe(1)
        ->and(WebhookEvent::query()->where('status', 'processed')->count())->toBe(1);
});

test('duplicate verified webhook is acknowledged without charging again', function () {
    $payment = makePlatformPayment();
    $payment->update(['provider' => 'kashier']);

    $data = [
        'amount' => '100.00',
        'currency' => 'EGP',
        'kashierOrderId' => 'KASHIER-ORDER-2',
        'merchantOrderId' => $payment->getKey(),
        'orderReference' => 'TEST-ORDER-2',
        'status' => 'SUCCESS',
        'transactionId' => 'TX-TEST-2',
        'signatureKeys' => ['transactionId', 'status', 'merchantOrderId', 'kashierOrderId', 'currency', 'amount'],
    ];

    $payload = ['event' => 'pay', 'data' => $data];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $headers = ['X-Kashier-Signature' => kashierSignature($data)];
    $handler = app(KashierWebhookHandler::class);

    expect($handler->handle($payload, $headers, $raw)['duplicate'])->toBeFalse()
        ->and($handler->handle($payload, $headers, $raw)['duplicate'])->toBeTrue();

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and(WebhookEvent::query()->count())->toBe(1);
});

test('invalid kashier webhook signature is rejected', function () {
    $payload = [
        'event' => 'pay',
        'data' => [
            'amount' => '100.00',
            'currency' => 'EGP',
            'merchantOrderId' => 'missing-payment',
            'status' => 'SUCCESS',
            'transactionId' => 'TX-INVALID',
            'signatureKeys' => ['transactionId', 'status', 'merchantOrderId', 'currency', 'amount'],
        ],
    ];

    $this->postJson('/webhooks/kashier/platform', $payload, [
        'X-Kashier-Signature' => 'invalid',
    ])->assertStatus(401);
});

test('kashier webhook failure does not activate the payment', function () {
    $payment = makePlatformPayment();
    $payment->update(['provider' => 'kashier']);

    $data = [
        'amount' => '100.00',
        'currency' => 'EGP',
        'merchantOrderId' => $payment->getKey(),
        'status' => 'FAILURE',
        'transactionId' => 'TX-FAIL-1',
        'signatureKeys' => ['transactionId', 'status', 'merchantOrderId', 'currency', 'amount'],
    ];

    $payload = ['event' => 'pay', 'data' => $data];

    $this->postJson('/webhooks/kashier/platform', $payload, [
        'X-Kashier-Signature' => kashierSignature($data),
    ])->assertOk();

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Failed);
});
