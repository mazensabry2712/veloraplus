# VeloraPlus — Phase 5 Billing

## Status

**IMPLEMENTATION IN PROGRESS — NOT CLOSED**

Phase 5 establishes the central VeloraPlus Billing domain and the provider-neutral payment boundaries. Kashier integration is intentionally deferred to Phase 6.

## Scope

Implemented:
- subscriptions and subscription items;
- monthly/yearly pricing;
- bundle discounts;
- 14-day trial;
- pending-payment activation;
- upgrades;
- scheduled downgrades;
- cancellation at period end;
- platform invoices and invoice lines;
- platform payment ledger with idempotency;
- refunds;
- separate credit ledger;
- billing audit records;
- subscription-driven entitlement projection;
- provider-neutral Platform/Tenant payment gateway contracts;
- capability-specific payment contracts;
- gateway manager.

Deferred:
- Kashier client/checkout;
- provider webhooks and signature verification;
- provider reconciliation;
- provider credential management;
- customer-facing payment UI;
- Tenant business payment implementation;
- usage metering;
- production recurring-provider charging.

## Payment domains

### Platform Billing — Company → VeloraPlus

```text
Company
  ↓
VeloraPlus Billing
  ↓
PlatformPaymentGateway
  ↓
External Provider
```

Platform Billing is central/control-database state.

### Tenant Payments — Customer → Company

```text
Customer
  ↓
Tenant Business Transaction
  ↓
TenantPaymentGateway
  ↓
Company Merchant Account
```

Tenant business payments belong to the Tenant business domain. These flows must not be conflated.

## Provider neutrality

Core Billing must not depend directly on Kashier or another concrete provider.

Contracts:

```text
PaymentGatewayInterface
├── PlatformPaymentGatewayInterface
└── TenantPaymentGatewayInterface

Capability contracts
├── CheckoutGateway
├── PaymentVerificationGateway
├── RefundGateway
├── RecurringPaymentGateway
├── WebhookGateway
└── TransactionLookupGateway
```

Adapters live in Infrastructure and are selected through PaymentGatewayManager.

Kashier is the first adapter planned for Phase 6. Adding another provider must not require rewriting Billing, Booking, CRM, ERP, or Entitlement business logic.

## Billing lifecycle

### New subscription with trial

```text
Catalog selection
  ↓
Pricing
  ↓
Trialing subscription
  ↓
Trialing subscription items
  ↓
Entitlements active
```

### Paid subscription

```text
Pricing
  ↓
Pending payment
  ↓
Open invoice
  ↓
Pending payment record
  ↓
Verified payment
  ↓
Active subscription
  ↓
Entitlements active
```

### Upgrade

```text
Active subscription
  ↓
New pending item
  ↓
Incremental invoice
  ↓
Payment
  ↓
Item active
  ↓
Entitlements updated
```

### Downgrade

```text
Active item
  ↓
Scheduled for removal
  ↓
Access remains until period end
  ↓
Entitlement ends
```

### Cancellation

```text
cancel_at_period_end = true
  ↓
Current paid period remains active
  ↓
No renewal
```

## Financial rules

- Money is represented by integer minor units.
- Floating-point money calculations are prohibited.
- Subscription and invoice prices are snapshot data.
- Historical invoice totals are never rewritten because catalog prices change.
- Refunds are separate financial records.
- Credits are separate from refunds.
- Platform invoices are Company → VeloraPlus.
- Tenant invoices are Company → Customer.

## Entitlement boundary

Billing is now the authoritative producer of subscription-based entitlement projection:

```text
Subscription + Subscription Items
            ↓
     EntitlementProjector
            ↓
 EntitlementService::rebuildProjection()
            ↓
    tenant_entitlements
```

Manual entitlements are preserved during rebuild.

## Tenant isolation

Every billing record is scoped by tenant_id.

A Tenant may only act on its own subscription, items, invoices, payments, refunds, credits, and billing audits.

## Audit events

Phase 5 records:
- subscription.created
- subscription.items_added
- subscription.item_removal_scheduled
- subscription.cancel_scheduled
- subscription.activated
- invoice.created
- invoice.paid
- payment.succeeded
- payment.failed
- refund.succeeded

## Test coverage

The Phase 5 suite covers:
- yearly pricing;
- bundle discount;
- trial behavior;
- paid subscription activation;
- upgrade billing/activation;
- scheduled downgrade;
- cancellation;
- invoice snapshotting;
- payment idempotency;
- duplicate success;
- partial/full refunds;
- credits;
- tenant isolation;
- billing audit events.

## Brick Money

Architecture documents specify Brick Money for application-level money handling.

The Phase 5 branch currently keeps the persisted representation as integer minor units and uses deterministic integer arithmetic in BillingMath.

Direct brick/money installation was not completed in this environment because external Composer registry access was unavailable. This must be resolved with a local Composer/lockfile update before production readiness is declared.

## Verification gate

```powershell
php artisan migrate
vendor/bin/pint --dirty --format agent
php artisan test --compact
git diff --check
git status
```

Then verify GitHub Actions.

Phase 5 must not be marked CLOSED / VERIFIED until local and CI verification pass.

## Next phase

Phase 6 — Payments & Provider Adapters.

First provider adapter: Kashier.

Tenant Customer → Company payment capabilities remain separate from Platform Billing.