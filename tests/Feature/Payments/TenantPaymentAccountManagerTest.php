<?php

use App\Application\Payments\TenantPaymentAccountManager;
use App\Models\PaymentProviderAccount;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('tenant payment account manager stores active credentials encrypted and supports deactivation', function () {
    $tenant = Tenant::factory()->create();

    $manager = app(TenantPaymentAccountManager::class);

    $account = $manager->upsert(
        tenant: $tenant,
        provider: 'KASHIER',
        accountReference: 'CLINIC-MAIN',
        credentials: [
            'merchant_id' => 'MID-CLINIC',
            'secret_key' => 'SECRET-CLINIC',
            'payment_api_key' => 'API-CLINIC',
            'merchant_redirect_url' => 'https://clinic.example/return',
            'webhook_url' => 'https://clinic.example/webhooks/kashier',
        ],
    );

    $raw = DB::connection('central')
        ->table('payment_provider_accounts')
        ->whereKey($account->getKey())
        ->value('encrypted_credentials');

    expect($account->provider)->toBe('kashier')
        ->and($account->status)->toBe('active')
        ->and($raw)->not->toContain('SECRET-CLINIC')
        ->and($account->fresh()->encrypted_credentials['merchant_id'])->toBe('MID-CLINIC');

    $deactivated = $manager->deactivate($account);

    expect($deactivated->status)->toBe('inactive');
});

test('active tenant payment account requires credentials', function () {
    $tenant = Tenant::factory()->create();

    expect(fn () => app(TenantPaymentAccountManager::class)->upsert(
        tenant: $tenant,
        provider: 'fake',
        accountReference: 'EMPTY',
        credentials: [],
    ))->toThrow(DomainException::class);
});
