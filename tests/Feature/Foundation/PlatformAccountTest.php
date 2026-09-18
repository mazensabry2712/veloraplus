<?php

use App\Models\PlatformAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the configured authentication model is the platform account', function () {
    expect(config('auth.providers.users.model'))->toBe(PlatformAccount::class);
});

test('a platform account is persisted in the central platform table', function () {
    $account = PlatformAccount::factory()->create([
        'name' => 'Foundation Account',
    ]);

    expect($account)
        ->toBeInstanceOf(PlatformAccount::class)
        ->and($account->getTable())->toBe('platform_accounts')
        ->and($account->status)->toBe('active');
});

test('platform account identifiers use ulids', function () {
    $account = PlatformAccount::factory()->create();

    expect($account->getKey())->toBeString()
        ->and(strlen($account->getKey()))->toBe(26);
});
