# VeloraPlus — Phase 7 Booking Module

## Status

**IN PROGRESS**

Phase 7 implements the first production business module on the existing VeloraPlus Core. Booking is tenant-owned, reuses the existing Company, Staff, Customer, Location, Entitlement, RBAC, and Tenant Payment boundaries, and must remain isolated from Platform Billing.

## 1. Locked boundaries

Booking data lives in the current Tenant database.

Platform Billing remains:

    Company → VeloraPlus

Tenant business payments remain:

    Customer → Company

Booking must consume TenantPaymentGateway through PaymentGatewayManager and must never write Platform Invoice/Platform Payment records for a customer booking.

## 2. Phase 7 delivery slices

### 7.1 Booking database + Services

Status: **COMPLETED IN THIS SLICE**

Implemented:

- tenant services table;
- ULID service identifiers;
- service name and description;
- duration and before/after buffers;
- integer minor-unit price;
- ISO-style three-letter currency storage;
- optional deposit amount bounded by price;
- service status;
- online booking visibility;
- capacity;
- metadata;
- soft deletion for archive;
- Service tenant model;
- ServiceStatus domain enum;
- ServiceManager create/update/archive application service;
- ServiceFactory;
- ServicePolicy;
- Booking service RBAC permissions;
- ready-tenant provisioning applies pending tenant migrations.

Validation rules:

- duration must be greater than zero;
- buffers cannot be negative;
- price cannot be negative;
- deposit cannot be negative or exceed price;
- capacity must be greater than zero;
- currency must be a three-letter code after normalization;
- status is limited to the current Booking service states.

### 7.2 Staff availability

Status: **PENDING**

Planned:

- working hours;
- breaks;
- time off;
- staff/service assignment;
- timezone-aware slot calculation.

### 7.3 Appointments

Status: **PENDING**

Planned:

- appointment creation;
- service/customer/staff/location linkage;
- start/end timestamps;
- appointment items;
- lifecycle;
- reschedule;
- cancel;
- payment state;
- idempotency where the request can be retried.

### 7.4 Tenant Payments integration

Status: **PENDING**

Planned:

- tenant business payment record;
- payment intent/checkout boundary;
- provider selection through PaymentGatewayManager;
- company merchant account selection;
- verified payment result handling;
- failure/pending/refund boundaries;
- no Platform Billing leakage.

### 7.5 Queue

Status: **PENDING**

Planned:

- queues;
- queue entries;
- business date;
- waiting/serving/completed/skipped/no-show transitions;
- safe concurrent position allocation and updates.

### 7.6 Public Booking

Status: **PENDING**

Planned:

- tenant public booking resolution;
- online-bookable services;
- staff selection when applicable;
- availability;
- time selection;
- customer details;
- confirmation;
- rate limiting;
- public-data minimization.

## 3. Authorization

Booking permissions are tenant-scoped through the existing RBAC team boundary.

Current permissions:

- booking.services.view
- booking.services.manage

Owner/admin receive both permissions.

Manager receives both permissions.

Staff and viewer receive view permission only.

Additional Booking permissions will be introduced with the relevant slice instead of creating the entire future permission matrix prematurely.

## 4. Entitlements

Booking business routes will be protected by both:

    tenant context
    ↓
    tenant membership
    ↓
    Booking entitlement
    ↓
    Booking permission/policy
    ↓
    application service

The Booking module is commercially controlled by the existing booking Module / booking.* Features in the central Catalog/Entitlement layer.

No UI-only entitlement check is considered sufficient.

## 5. Concurrency requirements

The implementation must prevent:

- double booking;
- race-condition overwrites;
- duplicate queue positions;
- repeated payment side effects.

Final appointment confirmation must perform an atomic availability re-check. Short-lived caches may support availability reads, but cache state is never authoritative for final booking allocation.

## 6. Tenant isolation requirements

- all Booking records are tenant-local;
- no cross-database foreign keys are allowed;
- a Tenant can never read another Tenant's Booking records;
- public Booking resolves exactly one verified active Tenant domain;
- central identifiers used by tenant records remain application-level references.

## 7. Testing requirements

Every Booking slice must include:

- happy-path feature tests;
- invalid-input negative tests;
- authorization tests;
- tenant-isolation tests where applicable;
- concurrency tests at race-prone boundaries;
- migration/schema coverage;
- provider/financial boundary tests where payments are involved.

## 8. Current verification target

For each slice, run:

    composer install
    php artisan migrate
    vendor/bin/pint --dirty --format agent
    php artisan test --compact
    git diff --check
    git status

Phase 7 cannot be marked CLOSED until Services, Availability, Appointments, Tenant Payments, Queue, Public Booking, authorization, isolation, concurrency, documentation, and final verification are complete.
