# VeloraPlus — Billing, Pricing & Payments

## 1. Billing model

VeloraPlus uses composable subscriptions rather than a single fixed plan as the only pricing mechanism.

~~~
Subscription
  ├── Module item
  ├── Feature item
  ├── Bundle item
  ├── Seat item (when enabled)
  └── Usage item (future / optional)
~~~

## 2. Plans / Bundles

Traditional plans may exist, but they are treated as curated Bundles rather than hard-coded feature gates.

Examples:

~~~
Starter
Business
Professional
Enterprise
~~~

A Bundle can contain Modules and/or Features and can apply a discount.

The customer may also construct a custom subscription.

## 3. Subscription

A Subscription belongs to exactly one Tenant. The MVP permits one open VeloraPlus Subscription per Tenant.

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

Recommended concepts:

- tenant;
- status;
- billing cycle;
- currency;
- country code;
- starts_at;
- trial_ends_at;
- current_period_start;
- current_period_end;
- next_billed_at;
- cancel_at_period_end;
- cancelled_at;
- provider;
- provider reference;
- amount snapshots;
- metadata.

## 4. Subscription item

Recommended concepts:

~~~
id
subscription_id
item_type
catalog_type
catalog_key / catalog_id
quantity
unit_amount_minor
currency
discount_amount_minor
tax_amount_minor
line_total_minor
starts_at
ends_at
status
activation_source
provider_reference
metadata
~~~

## 5. Price snapshotting

When an item is billed, the invoice preserves the historical price.

If the catalog changes later, old invoices do not change.

## 6. Pricing inputs

The pricing engine can depend on:

- Module;
- Feature;
- Bundle;
- Quantity;
- Seats;
- Usage;
- Country;
- Currency;
- Billing cycle;
- Discount;
- Tax.

Initial MVP billing focuses on Module, Feature, Bundle, and basic Seats if required. Usage billing remains future-ready.

## 7. Monthly / yearly pricing

The catalog stores explicit prices for each billing cycle.

Yearly pricing may be discounted through explicit configuration.

## 8. Trial

Default trial: 14 days.

During trial:

~~~
Subscription = trialing
Items = active
Entitlement source = trial
Entitlement ends_at = trial_ends_at
~~~

At trial end, Billing prepares the first paid invoice. Paid items stay pending until payment is verified.

## 9. Subscription states

MVP lifecycle:

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

pending_payment is the initial paid-subscription state and the renewal state before verified payment.

## 10. Upgrade

Adding a paid Module or Feature is an upgrade.

Default behavior:

- calculate incremental cost;
- create pending billing change;
- collect payment when required;
- wait for verified payment;
- activate immediately after confirmation.

Proration may be introduced later as an explicit Billing feature.

## 11. Downgrade

Removing a Module or Feature is normally end-of-period.

- mark item for removal;
- keep access for the current paid period;
- stop future renewal of that item;
- disable entitlement at the effective date;
- retain business data.

## 12. Cancellation

Default policy:

~~~
Cancel
=
stop future renewal
+
keep current paid period active
~~~

Automatic refunds are not assumed.

## 13. Refunds

Refunds create explicit financial records.

Concepts:

- refund;
- amount;
- source payment;
- provider refund id;
- status;
- reason;
- initiated_by;
- processed_at.

Never edit a paid invoice total to simulate a refund.

## 14. Credits

Credits are separate from refunds and can be applied to future periods.

## 15. Discounts

Support:

- Bundle discount;
- coupon/promo discount;
- future customer-specific discounts if required.

Discounts are calculated by Billing and snapshotted in invoices.

## 16. Tax

Conceptual invoice calculation:

~~~
Subtotal
- Discount
= Taxable subtotal
+ Tax
= Total
~~~

The actual tax algorithm is country/tenant dependent.

## 17. Money representation

Use integer minor units and a currency code.

Do not use floats.

Recommended:

~~~
amount_minor BIGINT
currency CHAR(3)
~~~

A money library/value object should be used in application code.

## 18. Payment provider architecture

Payment providers are external payment rails. Core Billing must remain provider-agnostic.

There are two separate payment domains.

### Platform Billing — Company → VeloraPlus

~~~
Company
  ↓
VeloraPlus Billing
  ↓
PlatformPaymentGateway
  ↓
Provider Adapter
  ↓
External Payment Provider
~~~

### Tenant Payments — Customer → Company

~~~
Customer
  ↓
Tenant Business Transaction
  ↓
TenantPaymentGateway
  ↓
Provider Adapter
  ↓
Company Merchant Account
~~~

The two domains remain financially and operationally separate:

- Platform Billing is owned by the central/control database.
- Tenant business payments belong to the current Tenant business domain.
- Platform Invoice = Company → VeloraPlus.
- Tenant Invoice = Company → Customer.
- Payment ownership, audit trail, provider references, and settlement context remain domain-specific.

### Provider-neutral adapter rule

Billing, Booking, CRM, and ERP code must not depend directly on Kashier or another concrete provider.

Use provider-neutral contracts plus a gateway manager/factory that selects a provider adapter by payment context.

Conceptual contracts:

~~~
PlatformPaymentGateway
TenantPaymentGateway

Capability contracts:
CheckoutGateway
PaymentVerificationGateway
RefundGateway
RecurringPaymentGateway
WebhookGateway
TransactionLookupGateway
~~~

A provider adapter may implement only the capabilities it supports.

**Kashier is the first provider adapter only. It is not part of Core Billing and is not the permanent payment provider.**

Adding another provider must not require rewriting Subscription, Invoice, Pricing, Entitlement, Booking, CRM, or ERP business logic.

## 19. VeloraPlus subscription payment flow

~~~
Company selects subscription
      ↓
Pricing Engine calculates
      ↓
Billing creates pending invoice/transaction
      ↓
Platform Payment Gateway
      ↓
Provider Adapter
      ↓
Provider payment
      ↓
Verified webhook
      ↓
Idempotency check
      ↓
Payment marked successful
      ↓
Subscription updated
      ↓
Subscription items activated
      ↓
Entitlements updated
~~~

Browser return/redirect is not the final authorization signal.

## 20. Webhook rules

Every payment-provider webhook must have:

- signature verification;
- provider event id;
- idempotency protection;
- payload validation;
- transaction-safe state changes;
- logging/audit trail;
- safe retry behavior.

The same webhook processed twice must not create duplicate payments or duplicate entitlements.

## 21. Company customer payments

Separate flow:

~~~
Customer
  ↓
Company business invoice/booking/sale
  ↓
Company payment gateway account
  ↓
Provider
  ↓
Payment recorded in tenant database
~~~

## 22. Merchant credentials

Merchant credentials must be encrypted, masked, tenant-isolated, and never logged in plaintext.

## 23. Platform invoice vs tenant invoice

Platform invoice:

Company → VeloraPlus.

Tenant invoice:

Company → Customer.

They are different financial domains and should not be mixed ambiguously.

## 24. Seat billing

An optional billable component can be extra users/seats.

Seat rules must be explicit.

Do not silently infer billing from raw row counts without a defined entitlement rule.

## 25. Usage billing

Future usage metrics may include messages, storage, API calls, appointments, and other metered units.

Usage data should support:

- metric;
- period;
- quantity;
- included allowance;
- overage quantity;
- overage rate.

## 26. Country pricing

Central catalog supports country/currency-specific prices.

Example:

~~~
Egypt → EGP
Saudi Arabia → SAR
UAE → AED
~~~

Company billing currency remains tenant-specific.

## 27. Billing source of truth

The internal VeloraPlus Billing domain is the business source of truth.

Payment providers are external payment rails.

## 28. Billing consistency

Subscription/payment/entitlement transitions that need atomicity use explicit transactions.

Cross-provider operations are designed for eventual consistency and retry.

## 29. Billing auditability

Every commercial state change should be explainable:

~~~
Tenant
→ Subscription
→ Invoice
→ Payment
→ Provider Event
→ Entitlement
~~~

Historical bills must remain reproducible.


## 30. Phase 5 implementation contract

Phase 5 implements the internal Billing domain without a concrete payment provider.

Implemented core responsibilities:

- Subscription lifecycle and one-open-subscription rule;
- Subscription Items and price snapshots;
- deterministic monthly/yearly pricing;
- Bundle discount calculation;
- 14-day trial;
- initial payment pending state;
- upgrade with pending payment;
- scheduled downgrade;
- cancellation at period end;
- renewal preparation;
- immutable Platform Invoices and Invoice Items;
- Platform Payment records;
- refunds and credits;
- billing audit records;
- Billing-driven entitlement projection.

Provider-specific checkout, verification, webhooks, reconciliation, and recurring agreements remain Phase 6. Kashier is an adapter, not Core Billing.
