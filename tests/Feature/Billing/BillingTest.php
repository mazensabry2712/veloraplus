<?php

use App\Application\Billing\CreditService;
use App\Application\Billing\PricingEngine;
use App\Application\Billing\RefundService;
use App\Application\Billing\SubscriptionService;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\PaymentStatus;
use App\Domain\Billing\SubscriptionItemStatus;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Billing\RefundStatus;
use App\Models\BillingAuditEvent;
use App\Models\CatalogPrice;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\PlatformRefund;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function billingModule(string $key): \App\Models\Module
{
    return \App\Models\Module::factory()->create([
        'key' => $key,
        'name' => $key,
        'status' => 'active',
    ]);
}

function billingFeature(\App\Models\Module $module, string $key): \App\Models\Feature
{
    return \App\Models\Feature::factory()->create([
        'module_id' => $module->getKey(),
        'key' => $key,
        'name' => $key,
        'status' => 'active',
        'is_individually_purchasable' => true,
    ]);
}

function billingPrice(\App\Models\Module|\App\Models\Feature|\App\Models\Bundle $item, int $amount, string $cycle = 'monthly', string $currency = 'EGP'): CatalogPrice
{
    return app(\App\Application\Catalog\CatalogManager::class)->addPrice($item, [
        'billing_cycle' => $cycle,
        'currency' => $currency,
        'amount_minor' => $amount,
        'effective_from' => now()->subMinute(),
    ]);
}

function paidSubscription(\App\Models\Tenant $tenant, array $items, string $cycle = 'monthly'): Subscription
{
    $subscription = app(SubscriptionService::class)->create(
        $tenant,
        $items,
        $cycle,
        0,
    );

    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();
    $payment = app(\App\Application\Billing\PaymentService::class)->createPending($invoice, null, null, null);
    app(\App\Application\Billing\PaymentService::class)->markSucceeded($payment);

    return $subscription->fresh('items');
}

test('pricing supports monthly and yearly cycles with integer money and tax', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $feature = billingFeature($module, 'booking.services');

    billingPrice($feature, 10000, 'monthly');
    billingPrice($feature, 108000, 'yearly');

    $monthly = app(PricingEngine::class)->quote($tenant, 'monthly', [
        ['item' => $feature, 'quantity' => 2],
    ], 'EGP', 'EG', 1500);

    $yearly = app(PricingEngine::class)->quote($tenant, 'yearly', [
        ['item' => $feature, 'quantity' => 1],
    ], 'EGP', 'EG');

    expect($monthly['subtotal_minor'])->toBe(20000)
        ->and($monthly['tax_minor'])->toBe(3000)
        ->and($monthly['total_minor'])->toBe(23000)
        ->and($yearly['total_minor'])->toBe(108000);
});

test('bundle discount is applied to the bundle catalog price before tax', function () {
    $tenant = Tenant::factory()->create();
    $bundle = app(\App\Application\Catalog\CatalogManager::class)->createBundle([
        'key' => 'booking-pro',
        'name' => 'Booking Pro',
        'status' => 'active',
        'discount_bps' => 1000,
    ]);

    billingPrice($bundle, 20000);

    $quote = app(PricingEngine::class)->quote($tenant, 'monthly', [
        ['item' => $bundle],
    ], 'EGP', 'EG');

    expect($quote['subtotal_minor'])->toBe(20000)
        ->and($quote['discount_minor'])->toBe(2000)
        ->and($quote['total_minor'])->toBe(18000);
});

test('a paid subscription stays pending until verified payment then activates entitlements', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000);

    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $feature],
    ], 'monthly', 0);

    expect($subscription->status)->toBe(SubscriptionStatus::PendingPayment)
        ->and($subscription->items->first()->status)->toBe(SubscriptionItemStatus::Pending)
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeFalse();

    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();
    $payment = app(\App\Application\Billing\PaymentService::class)->createPending($invoice);

    expect($payment->status)->toBe(PaymentStatus::Pending);

    app(\App\Application\Billing\PaymentService::class)->markSucceeded($payment);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->fresh('items')->items->first()->status)->toBe(SubscriptionItemStatus::Active)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeTrue();
});

test('failed initial payment does not activate the paid feature', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000);

    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $feature],
    ], 'monthly', 0);

    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();
    $payment = app(\App\Application\Billing\PaymentService::class)->createPending($invoice);

    app(\App\Application\Billing\PaymentService::class)->markFailed($payment, 'declined');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::PendingPayment)
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeFalse();
});

test('trial subscription grants a trial entitlement until the trial ends', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $feature = billingFeature($module, 'booking.services');
    billingPrice($feature, 7000);

    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $feature],
    ]);

    $trialEnd = $subscription->trial_ends_at;

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->current_period_start)->toEqual($subscription->starts_at)
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.services'))->toBeTrue()
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature(
            $tenant,
            'booking.services',
            $trialEnd->addSecond(),
        ))->toBeFalse();
});

test('invoice lines snapshot subscription item prices and remain immutable after catalog changes', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $feature = billingFeature($module, 'booking.queue');
    $price = billingPrice($feature, 5000);

    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $feature],
    ], 'monthly', 0);

    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();
    $line = $invoice->items()->firstOrFail();

    $price->update(['amount_minor' => 9000]);

    expect($line->unit_amount_minor)->toBe(5000)
        ->and($invoice->fresh()->total_minor)->toBe(5000);

    expect(fn () => $invoice->update(['total_minor' => 1]))
        ->toThrow(DomainException::class);

    expect(fn () => $invoice->delete())
        ->toThrow(DomainException::class)
        ->and(PlatformInvoice::query()->whereKey($invoice->getKey())->exists())->toBeTrue();
});

test('upgrade creates a separate pending subscription item and activates it only after payment', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $base = billingFeature($module, 'booking.services');
    $extra = billingFeature($module, 'booking.queue');
    billingPrice($base, 5000);
    billingPrice($extra, 3000);

    $subscription = paidSubscription($tenant, [['item' => $base]]);
    $invoice = app(SubscriptionService::class)->requestUpgrade($subscription, [
        ['item' => $extra],
    ]);

    $pending = $subscription->fresh('items')->items->firstWhere('catalog_key', 'booking.queue');

    expect($invoice->status)->toBe(InvoiceStatus::Open)
        ->and($pending->status)->toBe(SubscriptionItemStatus::Pending);

    expect(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeFalse();

    $payment = app(\App\Application\Billing\PaymentService::class)->createPending($invoice);
    app(\App\Application\Billing\PaymentService::class)->markSucceeded($payment);

    expect($subscription->fresh('items')->items->firstWhere('catalog_key', 'booking.queue')->status)
        ->toBe(SubscriptionItemStatus::Active)
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeTrue();
});

test('downgrade is scheduled for period end and preserves access until then', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $base = billingFeature($module, 'booking.services');
    $removable = billingFeature($module, 'booking.queue');
    billingPrice($base, 5000);
    billingPrice($removable, 3000);

    $subscription = paidSubscription($tenant, [
        ['item' => $base],
        ['item' => $removable],
    ]);

    $item = $subscription->fresh('items')->items->firstWhere('catalog_key', 'booking.queue');
    app(SubscriptionService::class)->scheduleDowngrade($subscription->fresh(), $item->fresh());

    $end = $subscription->fresh()->current_period_end;

    expect(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeTrue()
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature(
            $tenant,
            'booking.queue',
            $end->addSecond(),
        ))->toBeFalse();
});

test('cancellation keeps access until the period ends then closes the subscription', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000);

    $subscription = paidSubscription($tenant, [['item' => $feature]]);
    app(SubscriptionService::class)->cancelAtPeriodEnd($subscription->fresh());

    $end = $subscription->fresh()->current_period_end;

    expect(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeTrue();

    app(SubscriptionService::class)->processPeriodEnd($subscription->fresh(), $end);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeFalse();
});

test('refunds are separate financial records and never mutate invoice totals', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000);

    $subscription = paidSubscription($tenant, [['item' => $feature]]);
    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();
    $payment = $invoice->payments()->where('status', PaymentStatus::Succeeded->value)->firstOrFail();

    $refund = app(RefundService::class)->createPending($payment, 2000, 'customer request');
    app(RefundService::class)->markSucceeded($refund, 'refund-1');

    expect($refund->fresh()->status)->toBe(RefundStatus::Succeeded)
        ->and($invoice->fresh()->total_minor)->toBe(5000)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

test('fully refunded payments become refunded without changing the invoice history', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000);

    $subscription = paidSubscription($tenant, [['item' => $feature]]);
    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();
    $payment = $invoice->payments()->where('status', PaymentStatus::Succeeded->value)->firstOrFail();

    $refund = app(RefundService::class)->createPending($payment, 5000);
    app(RefundService::class)->markSucceeded($refund);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('credits are stored separately from refunds', function () {
    $tenant = Tenant::factory()->create();

    $credit = app(CreditService::class)->issue(
        $tenant,
        2500,
        'EGP',
        'service recovery',
    );

    expect($credit->amount_minor)->toBe(2500)
        ->and($credit->remaining_minor)->toBe(2500);
});

test('billing data is isolated by tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $module = billingModule('booking');
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000);

    $subscription = paidSubscription($tenantA, [['item' => $feature]]);

    expect(Subscription::query()->where('tenant_id', $tenantA->getKey())->whereKey($subscription->getKey())->exists())->toBeTrue()
        ->and(PlatformInvoice::query()->where('tenant_id', $tenantB->getKey())->exists())->toBeFalse()
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenantB, 'booking.queue'))->toBeFalse();
});

test('billing actions create an auditable trail', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000);

    paidSubscription($tenant, [['item' => $feature]]);

    expect(BillingAuditEvent::query()
        ->where('tenant_id', $tenant->getKey())
        ->whereIn('action', [
            'subscription.created_pending_payment',
            'invoice.created',
            'invoice.paid',
            'payment.succeeded',
            'subscription.items_activated',
        ])
        ->count()
    )->toBeGreaterThanOrEqual(5);
});

test('a tenant cannot have two open subscriptions', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000);

    app(SubscriptionService::class)->create($tenant, [['item' => $feature]], 'monthly', 0);

    expect(fn () => app(SubscriptionService::class)->create(
        $tenant,
        [['item' => $feature]],
        'monthly',
        0,
    ))->toThrow(DomainException::class);
});
