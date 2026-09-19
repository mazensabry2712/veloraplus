<?php

use App\Models\PlatformAccount;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('fortify authentication views render', function () {
    $this->get('/login')->assertOk();
    $this->get('/register')->assertOk();
    $this->get('/forgot-password')->assertOk();
    $this->get('/reset-password/test-token')->assertOk();

    $account = PlatformAccount::factory()->unverified()->create();

    $this->actingAs($account)
        ->get('/email/verify')
        ->assertOk();
});

test('an active platform account can authenticate', function () {
    $account = PlatformAccount::factory()->create([
        'email' => 'active@example.com',
        'password' => 'password',
    ]);

    $this->post('/login', [
        'email' => 'ACTIVE@EXAMPLE.COM',
        'password' => 'password',
    ])
        ->assertRedirect(config('fortify.home'));

    $this->assertAuthenticatedAs($account);
});

test('invalid credentials do not authenticate', function () {
    PlatformAccount::factory()->create([
        'email' => 'active@example.com',
        'password' => 'password',
    ]);

    $this->from('/login')
        ->post('/login', [
            'email' => 'active@example.com',
            'password' => 'wrong-password',
        ])
        ->assertRedirect('/login');

    $this->assertGuest();
});

test('suspended accounts cannot authenticate', function () {
    PlatformAccount::factory()->suspended()->create([
        'email' => 'suspended@example.com',
        'password' => 'password',
    ]);

    $this->from('/login')
        ->post('/login', [
            'email' => 'suspended@example.com',
            'password' => 'password',
        ])
        ->assertRedirect('/login');

    $this->assertGuest();
});

test('registration creates a central platform account and sends verification', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Registered Account',
        'email' => 'REGISTRATION@EXAMPLE.COM',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(config('fortify.home'));

    $account = PlatformAccount::query()
        ->where('email', 'registration@example.com')
        ->first();

    expect($account)->not->toBeNull()
        ->and($account->status)->toBe('active')
        ->and($account->email_verified_at)->toBeNull();

    Notification::assertSentTo($account, VerifyEmail::class);
    $this->assertAuthenticatedAs($account);
});

test('password reset request sends a reset notification', function () {
    Notification::fake();

    $account = PlatformAccount::factory()->create();

    $this->post('/forgot-password', [
        'email' => $account->email,
    ])->assertRedirect(config('fortify.home'));

    Notification::assertSentTo($account, ResetPassword::class);
});

test('password reset changes the account password', function () {
    $account = PlatformAccount::factory()->create([
        'password' => 'old-password',
    ]);

    $token = Password::broker()->createToken($account);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $account->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect('/login');

    expect(Hash::check('new-password', $account->fresh()->password))->toBeTrue();
});

test('email verification marks the account as verified', function () {
    $account = PlatformAccount::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(10),
        [
            'id' => $account->getKey(),
            'hash' => sha1($account->getEmailForVerification()),
        ],
    );

    $this->actingAs($account)
        ->get($verificationUrl)
        ->assertRedirect(config('fortify.home').'?verified=1');

    expect($account->fresh()->hasVerifiedEmail())->toBeTrue();
});
