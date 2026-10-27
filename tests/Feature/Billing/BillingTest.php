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

test('period end creates one renewal invoice and repeated processing cannot duplicate it', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000);

    $subscription = paidSubscription($tenant, [['item' => $feature]]);
    $periodEnd = $subscription->fresh()->current_period_end;
    $invoiceCount = $subscription->invoices()->count();

    $renewalInvoice = app(SubscriptionService::class)->processPeriodEnd($subscription, $periodEnd);

    expect($renewalInvoice)->toBeInstanceOf(PlatformInvoice::class)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::PendingPayment)
        ->and($subscription->invoices()->count())->toBe($invoiceCount + 1)
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeFalse();

    expect(fn () => app(SubscriptionService::class)->processPeriodEnd($subscription->fresh(), $periodEnd))
        ->toThrow(\DomainException::class)
        ->and($subscription->invoices()->count())->toBe($invoiceCount + 1);
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


test('subscription quotes add a shared billable dependency exactly once with an explanation', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('sales');
    $orders = billingFeature($module, 'sales.orders');
    $customers = billingFeature($module, 'sales.customers');
    $reporting = billingFeature($module, 'sales.reporting');

    billingPrice($orders, 5000);
    billingPrice($reporting, 4000);
    billingPrice($customers, 2000);

    $catalog = app(\App\Application\Catalog\CatalogManager::class);
    $catalog->addDependency($orders, $customers, 'billable');
    $catalog->addDependency($reporting, $customers, 'billable');

    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $orders],
        ['item' => $reporting],
    ], 'monthly', 0);

    $items = $subscription->fresh('items')->items;
    $dependencyItem = $items->firstWhere('catalog_key', 'sales.customers');

    expect($items)->toHaveCount(3)
        ->and($subscription->total_minor)->toBe(11000)
        ->and($dependencyItem)->not->toBeNull()
        ->and($dependencyItem->metadata['commercial_origin'])->toBe('required_dependency')
        ->and($dependencyItem->metadata['required_by'])->toEqualCanonicalizing(['sales.orders', 'sales.reporting']);
});

test('included dependencies are granted without creating a second billable line', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('sales');
    $orders = billingFeature($module, 'sales.orders');
    $customers = billingFeature($module, 'sales.customers');
    billingPrice($orders, 5000);
    billingPrice($customers, 2000);

    app(\App\Application\Catalog\CatalogManager::class)->addDependency($orders, $customers, 'included');

    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $orders],
    ]);

    expect($subscription->fresh('items')->items)->toHaveCount(1)
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'sales.orders'))->toBeTrue()
        ->and(app(\App\Application\Entitlements\EntitlementService::class)->hasFeature($tenant, 'sales.customers'))->toBeTrue();
});


test('commercial selection rejects a feature already covered by a selected bundle', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $feature = billingFeature($module, 'booking.appointments');
    $bundle = app(\App\Application\Catalog\CatalogManager::class)->createBundle([
        'key' => 'booking-pro',
        'name' => 'Booking Pro',
        'status' => 'active',
    ]);
    app(\App\Application\Catalog\CatalogManager::class)->addFeatureToBundle($bundle, $feature);

    expect(fn () => app(SubscriptionService::class)->create($tenant, [
        ['item' => $feature],
        ['item' => $bundle],
    ]))->toThrow(DomainException::class, 'already covered');
});


test('transitive billable dependencies are expanded once into the subscription quote', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('sales');
    $orders = billingFeature($module, 'sales.orders');
    $customers = billingFeature($module, 'sales.customers');
    $contacts = billingFeature($module, 'sales.contacts');

    billingPrice($orders, 5000);
    billingPrice($customers, 2000);
    billingPrice($contacts, 1000);

    $catalog = app(\App\Application\Catalog\CatalogManager::class);
    $catalog->addDependency($orders, $customers, 'billable');
    $catalog->addDependency($customers, $contacts, 'billable');

    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $orders],
    ], 'monthly', 0);

    expect($subscription->fresh('items')->items)->toHaveCount(3)
        ->and($subscription->total_minor)->toBe(8000)
        ->and($subscription->invoices()->latest('issued_at')->firstOrFail()->items)->toHaveCount(3);
});

test('upgrade does not bill a billable dependency already covered by the subscription', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('sales');
    $existingFeature = billingFeature($module, 'sales.customers');
    $newFeature = billingFeature($module, 'sales.orders');
    billingPrice($existingFeature, 2000);
    billingPrice($newFeature, 5000);

    $subscription = paidSubscription($tenant, [['item' => $existingFeature]]);
    app(\App\Application\Catalog\CatalogManager::class)->addDependency($newFeature, $existingFeature, 'billable');

    $invoice = app(SubscriptionService::class)->requestUpgrade($subscription->fresh(), [
        ['item' => $newFeature],
    ]);

    expect($invoice->total_minor)->toBe(5000)
        ->and($invoice->items)->toHaveCount(1)
        ->and($invoice->items->first()->catalog_key)->toBe('sales.orders');
});



test('upgrade preview is read-only and matches the invoice amount created for the same selection', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $base = billingFeature($module, 'booking.services');
    $extra = billingFeature($module, 'booking.queue');
    billingPrice($base, 5000);
    billingPrice($extra, 3000);

    $subscription = paidSubscription($tenant, [['item' => $base]]);
    $subscription->load('items');
    $itemCountBefore = $subscription->items->count();
    $invoiceCountBefore = $subscription->invoices()->count();

    $preview = app(SubscriptionService::class)->previewUpgrade($subscription, [
        ['item' => $extra, 'quantity' => 2],
    ]);

    expect($preview['subtotal_minor'])->toBe(6000)
        ->and($preview['total_minor'])->toBe(6000)
        ->and($preview['lines'])->toHaveCount(1)
        ->and($subscription->fresh('items')->items)->toHaveCount($itemCountBefore)
        ->and($subscription->invoices()->count())->toBe($invoiceCountBefore);

    $invoice = app(SubscriptionService::class)->requestUpgrade($subscription->fresh(), [
        ['item' => $extra, 'quantity' => 2],
    ]);

    expect($invoice->subtotal_minor)->toBe($preview['subtotal_minor'])
        ->and($invoice->total_minor)->toBe($preview['total_minor']);
});




test('repeated upgrade requests re-check locked subscription items and do not create duplicate invoices', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $base = billingFeature($module, 'booking.services');
    $extra = billingFeature($module, 'booking.queue');
    billingPrice($base, 5000);
    billingPrice($extra, 3000);

    $subscription = paidSubscription($tenant, [['item' => $base]]);
    $service = app(SubscriptionService::class);

    $invoice = $service->requestUpgrade($subscription, [['item' => $extra]]);
    $itemsAfterFirstRequest = $subscription->fresh('items')->items->count();
    $invoicesAfterFirstRequest = $subscription->invoices()->count();

    expect(fn () => $service->requestUpgrade($subscription, [['item' => $extra]]))
        ->toThrow(DomainException::class, "Catalog item {$extra->key} is already part of the subscription.")
        ->and($subscription->fresh('items')->items)->toHaveCount($itemsAfterFirstRequest)
        ->and($subscription->invoices()->count())->toBe($invoicesAfterFirstRequest)
        ->and($invoice->fresh()->status->value)->toBe('open');
});


test('commercial dependency resolution rejects inactive transitive requirements before billing', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('sales');
    $orders = billingFeature($module, 'sales.orders');
    $customers = billingFeature($module, 'sales.customers');
    $customers->update(['status' => 'retired']);

    billingPrice($orders, 5000);
    billingPrice($customers, 2000);

    app(\App\Application\Catalog\CatalogManager::class)
        ->addDependency($orders, $customers, 'billable');

    expect(fn () => app(SubscriptionService::class)->create($tenant, [
        ['item' => $orders],
    ], 'monthly', 0))->toThrow(DomainException::class, 'not active')
        ->and(Subscription::query()->where('tenant_id', $tenant->getKey())->exists())->toBeFalse()
        ->and(PlatformInvoice::query()->where('tenant_id', $tenant->getKey())->exists())->toBeFalse();
});

test('commercial dependency resolution refuses to silently undercharge a partially covered quantity', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('sales');
    $customers = billingFeature($module, 'sales.customers');
    $orders = billingFeature($module, 'sales.orders');

    billingPrice($customers, 2000);
    billingPrice($orders, 5000);

    $subscription = paidSubscription($tenant, [
        ['item' => $customers, 'quantity' => 1],
    ]);

    app(\App\Application\Catalog\CatalogManager::class)
        ->addDependency($orders, $customers, 'billable');

    expect(fn () => app(SubscriptionService::class)->previewUpgrade($subscription, [
        ['item' => $orders, 'quantity' => 2],
    ]))->toThrow(DomainException::class, 'lower quantity');

    expect($subscription->fresh('items')->items)->toHaveCount(1)
        ->and($subscription->invoices()->count())->toBe(1);
});


test('purchasing an individual Feature does not grant its parent Module or sibling Features', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('crm');
    $contacts = billingFeature($module, 'crm.contacts');
    $leads = billingFeature($module, 'crm.leads');
    billingPrice($contacts, 5000);

    paidSubscription($tenant, [['item' => $contacts]]);

    $entitlements = app(\App\Application\Entitlements\EntitlementService::class);

    expect($entitlements->hasFeature($tenant, 'crm.contacts'))->toBeTrue()
        ->and($entitlements->hasModule($tenant, 'crm'))->toBeFalse()
        ->and($entitlements->hasFeature($tenant, 'crm.leads'))->toBeFalse();
});

test('purchasing a Module grants its active Features but not inactive Features', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $services = billingFeature($module, 'booking.services');
    $appointments = billingFeature($module, 'booking.appointments');
    $retired = \App\Models\Feature::factory()->create([
        'module_id' => $module->getKey(),
        'key' => 'booking.legacy',
        'name' => 'booking.legacy',
        'status' => 'retired',
        'is_individually_purchasable' => false,
    ]);
    billingPrice($module, 12000);

    paidSubscription($tenant, [['item' => $module]]);

    $entitlements = app(\App\Application\Entitlements\EntitlementService::class);

    expect($entitlements->hasModule($tenant, 'booking'))->toBeTrue()
        ->and($entitlements->hasFeature($tenant, 'booking.services'))->toBeTrue()
        ->and($entitlements->hasFeature($tenant, 'booking.appointments'))->toBeTrue()
        ->and($entitlements->hasFeature($tenant, 'booking.legacy'))->toBeFalse();
});


test('failed initial invoice creation rolls back the pending subscription and its items', function () {
    $tenant = Tenant::factory()->create();
    $module = billingModule('booking');
    $feature = billingFeature($module, 'booking.queue');
    billingPrice($feature, 5000);

    \Illuminate\Support\Facades\DB::listen(function (\Illuminate\Database\Events\QueryExecuted $query): void {
        if (str_starts_with(strtolower(ltrim($query->sql)), 'insert into "platform_invoices"')) {
            throw new RuntimeException('invoice creation failed');
        }
    });

    expect(fn () => app(SubscriptionService::class)->create($tenant, [
        ['item' => $feature],
    ], 'monthly', 0))->toThrow(RuntimeException::class, 'invoice creation failed');

    expect(Subscription::query()->where('tenant_id', $tenant->getKey())->count())->toBe(0)
        ->and(\App\Models\SubscriptionItem::query()->count())->toBe(0)
        ->and(PlatformInvoice::query()->where('tenant_id', $tenant->getKey())->count())->toBe(0);
});
