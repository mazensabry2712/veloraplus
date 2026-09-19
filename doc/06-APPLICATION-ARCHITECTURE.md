# VeloraPlus — Laravel Application Architecture

## 1. Technology baseline

### Backend

- Laravel 13
- PHP 8.4
- MySQL 8.4
- Redis
- Eloquent
- Laravel Fortify
- Spatie Laravel Permission
- Stancl Tenancy
- Pest
- Larastan
- Laravel Pint
- Brick Money

### Frontend

- Blade
- Tailwind CSS 4
- Alpine.js
- Vanilla JavaScript
- Vite

## 2. Frontend philosophy

The UI is server-rendered with Blade.

Use:

- Blade for page/layout rendering;
- Alpine.js for light reactive UI state;
- Vanilla JavaScript modules for complex browser interactions;
- fetch() for AJAX/fetching where appropriate;
- Vite for bundling;
- Tailwind for styling.

Do not introduce React, Vue, Inertia, or Livewire unless a future architecture decision explicitly changes this baseline.

## 3. JavaScript boundaries

Recommended:

~~~
resources/js/
├── app.js
├── bootstrap.js
├── core/
│   ├── http/
│   ├── events/
│   ├── ui/
│   └── utilities/
├── components/
├── pages/
│   ├── dashboard/
│   ├── billing/
│   └── customer/
└── modules/
    ├── booking/
    ├── crm/
    └── erp/
~~~

## 4. Laravel structure

This project is intentionally a modular monolith.

Preferred conceptual organization:

~~~
app/
├── Domain/
│   ├── Platform/
│   ├── Identity/
│   ├── Tenancy/
│   ├── Billing/
│   └── Modules/
├── Application/
│   ├── Platform/
│   ├── Identity/
│   ├── Billing/
│   └── Modules/
├── Infrastructure/
│   ├── Persistence/
│   ├── Payments/
│   ├── Tenancy/
│   └── Notifications/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Models/
└── Providers/
~~~

Physical organization can be refined to match real Laravel conventions, but responsibility boundaries are mandatory.

## 5. Core domains

Identity:
- authentication;
- platform account;
- sessions;
- OTP;
- membership;
- roles/permissions.

Tenancy:
- tenant resolution;
- domain resolution;
- tenant database connection;
- tenant lifecycle;
- tenant context.

Billing:
- catalog;
- pricing;
- bundles;
- subscriptions;
- subscription items;
- invoices;
- payments;
- refunds;
- credits;
- webhooks;
- entitlement changes.

Shared business identity:
- company;
- staff;
- customers;
- locations.

Notifications:
- templates;
- channels;
- delivery attempts;
- statuses.

Audit:
- who changed what;
- tenant;
- entity;
- timestamp;
- metadata.

## 6. Module boundaries

A business Module should own:

- routes;
- controllers;
- requests;
- application services;
- domain logic;
- policies;
- views;
- JavaScript;
- migrations;
- tests.

A mature Module can follow:

~~~
Module
├── Domain
├── Application
├── Infrastructure
├── Http
├── Providers
├── Resources
└── routes.php
~~~

## 7. Dependency direction

Preferred:

~~~
Http
  ↓
Application
  ↓
Domain
  ↓
Infrastructure adapters
~~~

Domain code should not depend on Blade controllers.

## 8. Controllers

Controllers should be thin:

- authorize;
- validate;
- call an application action/service;
- return a response.

Business calculations do not belong in controllers.

## 9. Form Requests

Use Laravel Form Requests for meaningful validation and authorization.

Avoid duplicating request rules across multiple controllers.

## 10. Policies

Record-level access belongs in Policies/Gates.

Examples:

~~~
AppointmentPolicy
CustomerPolicy
StaffPolicy
InvoicePolicy
~~~

## 11. Entitlement middleware

Protected Module routes may use centralized entitlement middleware.

Concept:

~~~
route
  ↓
auth
  ↓
tenant
  ↓
entitlement
  ↓
permission/policy
  ↓
controller
~~~

## 11A. Domain resolution boundary

Tenant resolution must be domain-driven before tenant business data is accessed.

Concept:

~~~
Incoming Request
    ↓
Trusted Host Normalization
    ↓
Tenant Domain Resolver
    ↓
Verified Active Domain Record
    ↓
Tenant Context
    ↓
Tenant Database Selection
    ↓
Membership / Entitlement / Permission
    ↓
Controller / Business Service
~~~

Custom-domain infrastructure must not be spread through Controllers or business modules.

Recommended application boundaries:

- TenantDomainResolver — maps a trusted request hostname to the central Tenant registry.
- DomainVerificationService — validates customer control of a hostname.
- DomainLifecycleService — manages pending/active/failed/disabled state transitions.
- CustomDomainProviderInterface — provider-neutral boundary for edge/SSL/domain operations.
- DomainHealthCheck or equivalent queued process — periodically reconciles DNS/SSL/provider state.

The provider adapter may live under Infrastructure/ and must not leak provider-specific payloads into the Domain, Tenancy, Billing, or Booking services.

## 12. Billing services

Keep billing responsibilities explicit:

~~~
PricingEngine
SubscriptionService
InvoiceService
PaymentService
RefundService
CreditService
BillingAuditLogger
BillingEntitlementProjector
EntitlementService
~~~

Provider webhook processing remains in Phase 6 and uses the provider-neutral payment contracts.

Do not create one giant billing service.

## 13. Pricing Engine

Accept normalized inputs:

~~~
Tenant
Billing cycle
Currency
Selected catalog items
Quantities
Discounts
Tax context
~~~

Return deterministic price breakdown:

~~~
subtotal
discount
taxable_subtotal
tax
total
currency
lines[]
~~~

## 14. Payment adapters

Payment integration is provider-neutral and has two business contexts.

Platform Billing:

~~~
Contracts/
  PlatformPaymentGateway

Company
  ↓
VeloraPlus Billing
  ↓
PlatformPaymentGateway
  ↓
Provider Adapter
~~~

Tenant business payments:

~~~
Contracts/
  TenantPaymentGateway

Customer
  ↓
Tenant Business Transaction
  ↓
TenantPaymentGateway
  ↓
Provider Adapter
~~~

Provider adapters live under Infrastructure and may implement capability-specific contracts for checkout, verification, refunds, recurring payments, webhook verification, and transaction lookup.

Conceptual infrastructure:

~~~
Infrastructure/Payments/
  Kashier/
    KashierGateway
    KashierWebhookVerifier
    KashierClient
  OtherProvider/
    ...
~~~

Kashier is the first adapter, not a Core Billing dependency. Additional providers must be addable without changing Billing, Booking, CRM, or ERP business logic.

Do not leak provider payloads throughout the Domain or Application layers.

## 15. Events

Examples:

~~~
TenantCreated
SubscriptionActivated
FeatureActivated
FeatureScheduledForRemoval
CustomerCreated
AppointmentCreated
PaymentCompleted
PaymentFailed
InvoicePaid
~~~

Event consumers must be idempotent.

## 16. Jobs

Use queues for:

- email;
- notifications;
- report exports;
- imports;
- webhook processing;
- recurring billing;
- usage aggregation.

Jobs must restore correct tenant context.

## 17. Notifications

Shared notification infrastructure should support:

- database/in-app;
- email;
- SMS;
- WhatsApp later;
- push later.

Modules emit business events; delivery handles channels.

## 18. Audit

Audit important operations:

- billing changes;
- permission changes;
- customer changes;
- staff changes;
- appointment changes;
- refunds;
- module activation/deactivation;
- administrative overrides.

## 19. Storage

Use Laravel Filesystem.

Tenant file namespace:

~~~
tenants/{tenant-public-id}/...
~~~

Sensitive files stay private.

## 20. Search

Start with MySQL-supported search for MVP.

Define a Search abstraction so Meilisearch or another engine can be added later without rewriting business services.

## 21. Reporting

Reports should read domain data through query/application services.

A report must not become a second business logic engine.

## 22. Localization

Tenant-specific:

- locale;
- timezone;
- currency;
- date format;
- number format;
- RTL/LTR.

## 23. Authorization layers

Recommended:

~~~
Authentication
    ↓
Tenant resolution
    ↓
Membership
    ↓
Tenant status
    ↓
Module entitlement
    ↓
Feature entitlement
    ↓
Permission
    ↓
Policy / record access
~~~

## 24. Caching

Potentially cache:

- module catalog;
- feature catalog;
- pricing catalog;
- resolved entitlements.

Tenant-sensitive keys must include tenant identity/version.

## 25. Observability

Baseline:

- application logs;
- failed jobs;
- payment webhook audit;
- tenant lifecycle logs;
- health checks;
- error monitoring;
- slow-query awareness.

Billing trace should be possible:

~~~
Tenant
→ Subscription
→ Invoice
→ Payment
→ Provider Event
→ Entitlement
~~~

Tenant routing trace should also be observable:

~~~
Request Host
→ Normalized Domain
→ Tenant
→ Tenant Database
→ Entitlement / Permission
→ Business Request
~~~

Custom Domain provisioning should record verification, provisioning, failure, and deactivation events without exposing secrets.

## 26. Testing philosophy

Tests cover business rules, not only HTTP codes.

High-value tests:

- tenant isolation;
- multi-company switching;
- customer privacy;
- feature purchase;
- dependency activation;
- immediate activation;
- scheduled removal;
- bundle discount;
- duplicate webhook;
- subscription lifecycle;
- payment failure;
- authorization;
- booking conflicts.

## 27. Dependency policy

Before adding a package:

1. verify Laravel compatibility;
2. verify maintenance/release health;
3. verify licensing;
4. verify security posture;
5. check whether Laravel-native behavior is sufficient;
6. document why it exists.

## 28. Frontend library policy

Foundation:
- Alpine.js
- Tailwind CSS
- Vite

Booking:
- FullCalendar
- Flatpickr
- Tom Select

Dashboard:
- Chart.js

UI helpers:
- SweetAlert2
- Lucide

CRM/ordering:
- SortableJS

Avoid unused libraries.

## 29. Code style

Follow Laravel conventions and repository rules:

- typed methods;
- explicit return types;
- curly braces;
- constructor property promotion where appropriate;
- descriptive names;
- PHPDoc for non-obvious types;
- Pint formatting;
- Pest tests.

## 30. Architectural rule

The application is a modular monolith.

Do not split Booking, CRM, and ERP into separate repositories/services merely because they are Modules.


## 31. Global-scale performance rule

VeloraPlus is intended for global usage and high concurrency.

Performance requirements are architectural:

- application nodes remain stateless and horizontally scalable;
- Redis is used for shared cache, queues, locks, and sessions where configured;
- tenant databases can be distributed across database hosts as scale grows;
- high-volume work is moved to queues when it does not need to block the request;
- high-traffic queries must be indexed, bounded, and monitored;
- tenant-sensitive cache keys always include tenant identity;
- public Booking traffic is rate-limited and concurrency-safe;
- static assets should be CDN/edge-ready;
- observability and load testing are part of production readiness.

See `doc/09-SCALABILITY-AND-PERFORMANCE.md` for the detailed scaling rules.
