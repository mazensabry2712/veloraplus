<?php

use App\Application\Billing\CreditService;
use App\Application\Billing\Pricing\PricingEngine;
use App\Application\Billing\RefundService;
use App\Application\Billing\SubscriptionService;
use App\Application\Catalog\CatalogManager;
use App\Domain\Billing\BillingCycle;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\PaymentStatus;
use App\Domain\Billing\RefundStatus;
use App\Domain\Billing\SubscriptionItemStatus;
use App\Domain\Billing\SubscriptionStatus;
use App\Models\PlatformCredit;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\PlatformRefund;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function billingModule(string $key = 'booking'): \App\Models\Module
{
    return \App\Models\Module::factory()->create([
        'key' => $key,
        'name' => ucfirst($key),
        'status' => 'active',
    ]);
}

function billingFeature(\App\Models\Module $module, string $key): \App\Models\Feature
{
    return \App\Models\Feature::factory()->create([
        'module_id' => $module->getKey(),
        'key' => $key,
        'name' => ucfirst(str_replace('.', ' ', $key)),
        'status' => 'active',
    ]);
}

function billingPrice(\App\Models\Module|\App\Models\Feature|\App\Models\Bundle $item, int $monthly, int $yearly, ?string $country = null): void
{
    $manager = app(\App\Application\Catalog\CatalogPriceManager::class);

    $manager->add($item, [
        'billing_cycle' => 'monthly',
        'currency' => 'EGP',
        'country_code' => $country,
        'amount_minor' => $monthly,
        'effective_from' => now()->subMinute(),
    ]);

    $manager->add($item, [
        'billing_cycle' => 'yearly',
        'currency' => 'EGP',
        'country_code' => $country,
        'amount_minor' => $yearly,
        'effective_from' => now()->subMinute(),
    ]);
}

test('pricing resolves the selected billing cycle and applies bundle discount', function () {
    $tenant = Tenant::factory()->create();
    $manager = app(CatalogManager::class);
    $engine = app(PricingEngine::class);

    $module = billingModule();
    $feature = billingFeature($module, 'booking.queue');

    billingPrice($module, 10000, 100000);
    billingPrice($feature, 5000, 50000);

    $bundle = $manager->createBundle([
        'key' => 'booking-pro',
        'name' => 'Booking Pro',
        'status' => 'active',
        'discount_bps' => 1000,
    ]);

    $manager->addModuleToBundle($bundle, $module);

    $breakdown = $engine->calculate(
        $tenant,
        [['item' => $bundle]],
        BillingCycle::Yearly,
    );

    expect($breakdown->subtotalMinor)->toBe(100000)
        ->and($breakdown->discountMinor)->toBe(10000)
        ->and($breakdown->totalMinor)->toBe(90000)
        ->and($breakdown->lines[0]->catalogPriceId)->toBeNull();
});

test('trial subscription activates entitlements without creating an invoice', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule();
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000, 50000);

    $subscription = app(SubscriptionService::class)->start(
        $tenant,
        [['item' => $feature]],
        BillingCycle::Monthly,
    );

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->trial_ends_at)->not->toBeNull()
        ->and($subscription->items()->first()->status)->toBe(SubscriptionItemStatus::Trialing)
        ->and(PlatformInvoice::query()->count())->toBe(0)
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeTrue();
});

test('paid subscription stays pending until a recorded payment succeeds', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule();
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000, 50000);

    $subscription = app(SubscriptionService::class)->start(
        $tenant,
        [['item' => $feature]],
        BillingCycle::Monthly,
        trial: false,
    );

    $invoice = $subscription->invoices()->firstOrFail();

    expect($subscription->status)->toBe(SubscriptionStatus::PendingPayment)
        ->and($invoice->status)->toBe(InvoiceStatus::Open)
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeFalse();

    $paymentService = app(\App\Application\Billing\PaymentService::class);
    $payment = $paymentService->createPending($invoice, $invoice->total_minor, 'initial-1');
    $paymentService->succeed($payment, 'external-1');

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->items()->first()->status)->toBe(SubscriptionItemStatus::Active)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeTrue();
});

test('upgrade invoice only bills the new pending item and activates it after payment', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule();
    $base = billingFeature($module, 'booking.services');
    $extra = billingFeature($module, 'booking.queue');
    billingPrice($base, 5000, 50000);
    billingPrice($extra, 3000, 30000);

    $subscription = app(SubscriptionService::class)->start(
        $tenant,
        [['item' => $base]],
        BillingCycle::Monthly,
        trial: false,
    );

    $initialInvoice = $subscription->invoices()->firstOrFail();
    $paymentService = app(\App\Application\Billing\PaymentService::class);
    $paymentService->succeed(
        $paymentService->createPending($initialInvoice, $initialInvoice->total_minor, 'initial-upgrade-test'),
    );

    $invoice = app(SubscriptionService::class)->addItems(
        $subscription->refresh(),
        [['item' => $extra]],
    );

    expect($invoice)->not->toBeNull()
        ->and($invoice->total_minor)->toBe(3000)
        ->and($subscription->items()->where('catalog_key', 'booking.queue')->first()->status)
        ->toBe(SubscriptionItemStatus::Pending)
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeFalse();

    $payment = $paymentService->createPending($invoice, 3000, 'upgrade-payment');
    $paymentService->succeed($payment, 'upgrade-external');

    expect($subscription->items()->where('catalog_key', 'booking.queue')->first()->refresh()->status)
        ->toBe(SubscriptionItemStatus::Active)
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeTrue()
        ->and(\App\Models\BillingAudit::query()->where('action', 'subscription.items_added')->exists())->toBeTrue();
});

test('scheduled downgrade keeps entitlement until period end', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule();
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000, 50000);

    $subscription = app(SubscriptionService::class)->start(
        $tenant,
        [['item' => $feature]],
        BillingCycle::Monthly,
    );

    $item = $subscription->items()->firstOrFail();

    app(SubscriptionService::class)->scheduleRemoval($item);

    expect($item->refresh()->status)->toBe(SubscriptionItemStatus::ScheduledForRemoval)
        ->and(\App\Models\BillingAudit::query()->where('action', 'subscription.item_removal_scheduled')->exists())->toBeTrue()
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeTrue()
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature(
            $tenant,
            'booking.queue',
            $subscription->current_period_end->addSecond(),
        ))->toBeFalse();
});

test('cancellation stops renewal without immediately removing access', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule();
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000, 50000);

    $subscription = app(SubscriptionService::class)->start($tenant, [['item' => $feature]]);

    app(SubscriptionService::class)->cancelAtPeriodEnd($subscription);

    expect($subscription->refresh()->cancel_at_period_end)->toBeTrue()
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeTrue();
});

test('invoice snapshots remain unchanged after the catalog price changes', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule();
    $feature = billingFeature($module, 'booking.queue');
    $manager = app(\App\Application\Catalog\CatalogPriceManager::class);

    $manager->add($feature, [
        'billing_cycle' => 'monthly',
        'currency' => 'EGP',
        'amount_minor' => 5000,
        'effective_from' => now()->subMinutes(2),
        'effective_to' => now()->addMinute(),
    ]);

    $manager->add($feature, [
        'billing_cycle' => 'yearly',
        'currency' => 'EGP',
        'amount_minor' => 50000,
        'effective_from' => now()->subMinutes(2),
    ]);

    $subscription = app(SubscriptionService::class)->start(
        $tenant,
        [['item' => $feature]],
        BillingCycle::Monthly,
        trial: false,
    );

    $invoice = $subscription->invoices()->firstOrFail();
    $invoiceLine = $invoice->items()->firstOrFail();

    expect($invoiceLine->unit_amount_minor)->toBe(5000);

    $manager->add($feature, [
        'billing_cycle' => 'monthly',
        'currency' => 'EGP',
        'amount_minor' => 9000,
        'effective_from' => now()->addMinutes(2),
    ]);

    expect($invoice->refresh()->total_minor)->toBe(5000)
        ->and($invoice->items()->first()->unit_amount_minor)->toBe(5000);
});

test('duplicate payment creation is idempotent and duplicate success is harmless', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule();
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000, 50000);

    $subscription = app(SubscriptionService::class)->start(
        $tenant,
        [['item' => $feature]],
        BillingCycle::Monthly,
        trial: false,
    );
    $invoice = $subscription->invoices()->firstOrFail();

    $service = app(\App\Application\Billing\PaymentService::class);
    $first = $service->createPending($invoice, 5000, 'idempotent-key');
    $second = $service->createPending($invoice, 5000, 'idempotent-key');

    $service->succeed($first, 'external-id');
    $third = $service->succeed($first, 'external-id-again');

    expect($second->getKey())->toBe($first->getKey())
        ->and($third->getKey())->toBe($first->getKey())
        ->and(PlatformPayment::query()->count())->toBe(1)
        ->and(PlatformPayment::query()->first()->status)->toBe(PaymentStatus::Succeeded);
});

test('partial and full refunds update payment and invoice states without changing the invoice total', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule();
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000, 50000);

    $subscription = app(SubscriptionService::class)->start(
        $tenant,
        [['item' => $feature]],
        BillingCycle::Monthly,
        trial: false,
    );
    $invoice = $subscription->invoices()->firstOrFail();
    $paymentService = app(\App\Application\Billing\PaymentService::class);
    $payment = $paymentService->createPending($invoice, 5000, 'refund-key');
    $paymentService->succeed($payment);

    $refunds = app(RefundService::class);
    $partial = $refunds->request($payment->refresh(), 2000, 'partial');
    $refunds->succeed($partial);

    expect($payment->refresh()->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::PartiallyRefunded);

    $rest = $refunds->request($payment->refresh(), 3000, 'remaining');
    $refunds->succeed($rest);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Refunded)
        ->and($invoice->total_minor)->toBe(5000)
        ->and(PlatformRefund::query()->where('status', RefundStatus::Succeeded->value)->count())->toBe(2);
});

test('credits remain a separate ledger from refunds', function () {
    $tenant = Tenant::factory()->create();

    $credits = app(CreditService::class);
    $credits->grant($tenant, 1500, 'EGP', 'manual', 'credit-1');

    expect($credits->balance($tenant, 'EGP'))->toBe(1500);
    expect(PlatformCredit::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1);
});

test('different tenants have isolated subscriptions and entitlements', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $module = billingModule();
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000, 50000);

    app(SubscriptionService::class)->start($tenantA, [['item' => $feature]]);

    expect($tenantA->subscriptions()->count())->toBe(1)
        ->and($tenantB->subscriptions()->count())->toBe(0)
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenantA, 'booking.queue'))->toBeTrue()
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenantB, 'booking.queue'))->toBeFalse();
});
