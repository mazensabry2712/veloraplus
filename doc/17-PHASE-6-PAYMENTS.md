# VeloraPlus — Phase 6 Payments & Provider Adapters

## Status

**IMPLEMENTED / VERIFICATION PENDING**

Phase 6 connects the provider-neutral payment boundaries to the first concrete provider adapter while preserving the separation between Platform Billing and Tenant Payments.

## 1. Scope

Implemented:

- provider registry/manager;
- Platform Payment Gateway boundary;
- Tenant Payment Gateway boundary;
- capability-based checkout, verification, refund, webhook, and transaction contracts;
- Kashier hosted Payment Session adapter;
- Kashier payment session lookup;
- Kashier refund adapter;
- Kashier webhook HMAC verification;
- webhook idempotency storage;
- verified Platform Payment activation through existing Billing services;
- provider-neutral tenant adapter boundary.

Not implemented:

- live credentials;
- production provider onboarding;
- provider-specific recurring agreements;
- saved-card/token workflows;
- direct card collection;
- tenant Booking payment UI/business flow;
- customer-facing checkout UI;
- Marketplace UI;
- automated settlement/reconciliation dashboards.

## 2. Financial boundaries

Platform Billing:

    Company
        ↓
    VeloraPlus Subscription
        ↓
    Platform Invoice
        ↓
    Platform Payment
        ↓
    PlatformPaymentGateway
        ↓
    Provider Adapter
        ↓
    Payment Provider

Tenant Payments:

    Customer
        ↓
    Tenant Booking / Sale / Invoice
        ↓
    TenantPaymentGateway
        ↓
    Provider Adapter
        ↓
    Company Merchant Account

These are separate financial domains.

## 3. Provider selection

PaymentGatewayManager resolves the configured Platform or Tenant provider through the provider registry.

A new provider is added as another adapter and registered without changing Core Billing rules.

## 4. Kashier hosted checkout

The Kashier adapter uses the hosted Payment Sessions API.

The payment amount remains an integer minor-unit value inside VeloraPlus and is formatted into the decimal representation expected by the provider.

The hosted session returns a session URL, which is the external payment destination.

## 5. Credentials and environments

No real credentials are committed.

Environment variables are provided through .env.example.

Kashier mode defaults to test. Live credentials and live URLs remain deployment configuration only.

## 6. Webhook security

The platform webhook endpoint is:

    POST /webhooks/kashier/platform

CSRF is intentionally disabled for this provider-to-server endpoint.

Before any financial mutation:

1. the payload is received;
2. x-kashier-signature is verified;
3. signatureKeys are sorted and the signed values are encoded;
4. HMAC-SHA256 is calculated with the Kashier Payment API Key;
5. only a verified payment event can enter Billing.

The webhook event field is not treated as a payment-success signal. Only payment events are processed, and the transaction data status is authoritative.

## 7. Webhook idempotency

webhook_events is a central table.

The provider event identifier is deterministically derived from provider, event, payment status, merchant order id, transaction id, and provider order id. The raw request body is stored separately as payload_hash.

A duplicate verified event is detected before repeating financial state changes.

The unique database rule is:

    (provider, provider_event_id)

## 8. Verified success path

    Kashier webhook
          ↓
    Signature verification
          ↓
    Idempotency check
          ↓
    PlatformPayment lookup
          ↓
    Amount + currency verification
          ↓
    PaymentService::markSucceeded()
          ↓
    Invoice paid
          ↓
    Subscription items active
          ↓
    Entitlement projection rebuilt

Browser redirects never activate access.

## 9. Failure and pending paths

FAILURE marks an existing pending Platform Payment as failed.

PENDING does not activate access.

Unknown provider events are ignored by the platform payment handler and do not mutate Billing state.

## 10. Refund adapter

The Kashier adapter supports the provider refund operation using the provider order identifier and optional transaction identifier and partial amount.

The internal RefundService remains the financial record source. Provider execution and internal refund state are separate responsibilities.

## 11. Tenant Payment boundary

KashierGateway also implements TenantPaymentGateway so the same provider can later be selected for customer-to-company payments.

This phase does not implement tenant Booking/Sales payment records. Those belong to their respective tenant business modules and consume the same provider capability layer.

## 12. Security rules

- no real secrets in source control;
- provider credentials remain in environment/configuration;
- webhook signatures are mandatory;
- browser redirects cannot activate billing;
- provider payloads are normalized before Application services consume them;
- provider-specific response structures do not leak into Core Billing;
- provider credentials are never logged;
- external calls use bounded timeout and retry settings.

## 13. Verification gate

Run locally:

    composer install
    php artisan migrate
    vendor/bin/pint --dirty --format agent
    php artisan test --compact
    git diff --check
    git status

Phase 6 can be marked CLOSED / VERIFIED only when the local suite and GitHub Actions both pass.

## 14. Next phase

Phase 7 — Booking Module.

Booking will use the existing shared Company, Staff, Customer, Tenant, Billing, Entitlement, and Payment infrastructure.
