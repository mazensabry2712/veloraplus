# VeloraPlus — Phase 5 Billing

## Status

**CLOSED / VERIFIED**

Phase 5 establishes the internal VeloraPlus Billing domain as the commercial source of truth between the central Catalog/Entitlements layers and the later Payment Provider integration.

This phase deliberately does not implement Kashier or any other external provider adapter.

## 1. Scope

Implemented:

- central subscriptions;
- subscription items;
- monthly and yearly billing cycles;
- configurable trial with 14 days as the product default;
- deterministic pricing;
- Bundle discount handling;
- tenant currency/country context;
- integer minor-unit money calculations with overflow-safe integer arithmetic;
- immutable invoice and invoice-line snapshots;
- platform payment records;
- verified-payment activation boundary;
- upgrade flow with pending payment;
- end-of-period downgrade;
- cancellation at period end;
- renewal preparation;
- refunds as separate financial records;
- credits as separate financial records;
- billing audit trail;
- billing-to-entitlement projection;
- provider-neutral payment capability contracts.

Not implemented in this phase:

- Kashier client;
- provider checkout pages;
- provider webhook endpoints;
- provider signature verification;
- external payment reconciliation;
- tenant customer payment implementation;
- recurring provider agreements;
- usage metering;
- production tax jurisdiction engine;
- Module Marketplace UI.

Those belong to Phase 6 or later modules.

## 2. Financial domain boundaries

VeloraPlus has two separate payment domains:

~~~
Platform Billing
Company → VeloraPlus

Tenant Payments
Customer → Company
~~~

Phase 5 implements only Platform Billing.

Tenant Payments remains a business-payment domain used by Booking/CRM/ERP modules and must not be confused with VeloraPlus Subscription Billing.

## 3. Provider-neutral rule

The Billing domain never depends directly on Kashier or another concrete provider.

The later payment layer will use:

~~~
PlatformPaymentGateway
TenantPaymentGateway

Capability contracts:
- CheckoutGateway
- PaymentVerificationGateway
- RefundGateway
- RecurringPaymentGateway
- WebhookGateway
- TransactionLookupGateway
~~~

A provider adapter may implement only the capabilities it supports.

Kashier will be the first adapter in Phase 6, not a Core Billing dependency.

## 4. Subscription source of truth

A Tenant has one open VeloraPlus Subscription in the MVP.

The Subscription and its Subscription Items are the financial source of truth for purchased platform capabilities.

tenant_entitlements remains an authorization projection and can be rebuilt from Billing state.

## 5. Subscription lifecycle

Supported states:

~~~
pending_payment
trialing
active
past_due
grace
suspended
cancelled
expired
~~~

MVP activation behavior:

- paid subscriptions start as pending_payment;
- trial subscriptions start as trialing;
- only verified payment transitions a paid subscription to active;
- cancelled subscriptions remain active for the paid period until cancellation becomes effective;
- scheduled item removals keep access until their ends_at;
- renewal can move the subscription back to pending_payment until payment succeeds.

past_due, grace, and suspended are represented for the future state machine; this phase only gives grace an entitlement-preserving interpretation when explicitly used.

## 6. Trial

Default trial length is 14 days.

During trial:

~~~
Subscription = trialing
Items = active
Entitlement source = trial
Entitlement ends_at = trial_ends_at
~~~

No payment provider call is required to start the trial.

At trial end, processPeriodEnd() prepares the first paid invoice. Paid items become pending until payment is verified.

## 7. Pricing

PricingEngine accepts:

~~~
Tenant
Billing cycle
Currency
Country
Catalog items
Quantities
Tax basis points
~~~

The result is deterministic:

~~~
subtotal
discount
taxable_subtotal
tax
total
lines[]
~~~

Pricing uses current active CatalogPrice records selected through the Phase 3 catalog resolver.

Catalog prices are copied into Subscription Items as price snapshots.

## 8. Bundle pricing

For Phase 5:

- a Bundle has an explicit catalog price;
- discount_bps applies to that Bundle catalog price;
- tax is calculated after the Bundle discount;
- the resulting discount/tax/line total is stored on the Subscription Item and Invoice Item;
- Bundle contents determine entitlement projection, not the monetary line calculation.

This rule is explicit so Bundle pricing does not depend on hidden component-price summation.

## 9. Money

Money is represented externally as:

~~~
amount_minor BIGINT
currency CHAR(3)
~~~

Application calculations use integer minor units and never floating-point arithmetic. Percentage calculations use basis points with explicit downward rounding.

Percentage calculations use basis points with explicit downward rounding for minor-unit results in this phase.

## 10. Subscription Items

Each billable item stores:

~~~
subscription
catalog type/key
catalog price id
quantity
unit amount snapshot
discount snapshot
tax snapshot
line total snapshot
currency
status
activation source
start/end window
provider reference
metadata
~~~

Supported MVP catalog item types:

- Module;
- Feature;
- Bundle.

Seat and Usage items remain future-ready but are not activated by this phase.

## 11. Invoice

Every billable subscription charge creates a Platform Invoice.

Invoice amounts are snapshots.

Once issued_at is set:

- invoice number is immutable;
- invoice currency is immutable;
- subtotal/discount/tax/total are immutable;
- Invoice Items are immutable;
- a refund never changes the historical Invoice Total.

Payment status is recorded separately.

## 12. Initial payment flow

Phase 5 internal activation boundary:

~~~
Company selects catalog items
      ↓
PricingEngine
      ↓
Subscription + Subscription Items
      ↓
Platform Invoice
      ↓
Platform Payment = pending
      ↓
Verified success recorded by PaymentService
      ↓
Invoice = paid
      ↓
Subscription = active
      ↓
Subscription Items = active
      ↓
Entitlement projection rebuilt
~~~

Phase 6 will place the external Provider Adapter before the verified-success call.

## 13. Upgrade

Adding a new Module/Feature to an active Subscription:

~~~
request upgrade
      ↓
new Subscription Item = pending
      ↓
upgrade Invoice = open
      ↓
verified payment
      ↓
new item = active
      ↓
entitlement projection rebuilt
~~~

An item cannot be duplicated in the same open Subscription during MVP.

## 14. Downgrade

A downgrade does not delete the business capability immediately.

~~~
active item
   ↓
scheduled
   ↓
ends_at = current_period_end
   ↓
inactive
~~~

Entitlements continue to work until the effective end date.

Required Features cannot be independently removed.

## 15. Cancellation

Cancellation means:

~~~
cancel_at_period_end = true
~~~

The current paid period remains active.

At period end:

- subscription becomes cancelled;
- items become inactive;
- entitlements are rebuilt without those items;
- business data is retained.

## 16. Renewal

At period end, when cancellation is not requested:

- scheduled items become inactive;
- remaining active items become pending;
- the subscription moves to pending_payment;
- the billing period advances;
- a renewal invoice is created;
- successful payment activates the pending renewal items.

This establishes the state-machine boundary without introducing provider-specific recurring-charge logic.

## 17. Refunds

Refunds are separate records linked to a successful Platform Payment.

Rules:

- refund amount cannot exceed the unreimbursed payment amount;
- multiple pending/succeeded refunds are bounded by the original payment;
- successful full refund changes the Payment status to refunded;
- Invoice totals remain unchanged.

Actual provider refund execution is deferred to the Phase 6 adapter.

## 18. Credits

Credits are separate from refunds.

A Credit records:

- original amount;
- remaining amount;
- currency;
- source;
- expiration;
- status;
- metadata.

Future invoice application can consume available credit without rewriting historical invoices.

## 19. Audit

Commercial state transitions generate central billing audit records.

Examples:

~~~
subscription.trial_started
subscription.created_pending_payment
subscription.upgrade_requested
subscription.item_downgrade_scheduled
subscription.cancellation_scheduled
subscription.cancelled
subscription.renewal_pending_payment
invoice.created
invoice.paid
payment.succeeded
payment.failed
refund.succeeded
credit.issued
~~~

Audit records are tenant-scoped and reference the affected subject.

## 20. Entitlement projection

Billing is the authoritative producer of subscription-derived entitlements.

The projector:

- projects active/trial/grace subscription items;
- expands Bundle Modules and Features;
- keeps scheduled removals active until ends_at;
- preserves active manual entitlement roots;
- rebuilds the central tenant_entitlements projection.

No payment redirect is allowed to directly grant entitlement access.

## 21. Tenant isolation

All Billing records are centrally tenant-scoped.

Every application service must operate using the Tenant id in the current platform context.

No tenant can use another tenant's Subscription, Invoice, Payment, Refund, Credit, or Audit records.

## 22. Verification gate

Completed verification:

~~~powershell
composer install
php artisan migrate
vendor/bin/pint --dirty --format agent
php artisan test --compact
git diff --check
git status
~~~

Verified locally:

- `composer install` completed without dependency changes.
- billing migration succeeded.
- Pint passed.
- full test suite passed: **65 tests, 216 assertions**.
- `git diff --check` passed.
- working tree is clean and synchronized with `origin/main`.
- GitHub Actions for the final Phase 5 hardening commit `ba37072486b932b93d65783b96ed2aa8b9d17468` succeeded.

Phase 5 is now CLOSED / VERIFIED.

## 23. Next phase

Phase 6 — Payments & Provider Adapters.

That phase adds the actual provider implementations, starting with Kashier, while preserving the provider-neutral contracts established here.
