<?php

use App\Application\Payments\TenantPaymentAccountManager;
use App\Models\PaymentProviderAccount;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    DB::setDefaultConnection('central');
    expect(Artisan::call('migrate:fresh', [
        '--database' => 'central',
        '--force' => true,
    ]))->toBe(0);
});

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

    $raw = $account->getRawOriginal('encrypted_credentials');

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
