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
- stable Tenant-local public slug for future SEO/public Service URLs;
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

Status: **COMPLETED IN THIS SLICE**

Implemented:

- tenant working-hours schema with ULID identifiers;
- multiple non-overlapping working intervals per staff member and weekday;
- recurring breaks attached to a working interval;
- date/time-specific staff time off stored as UTC-aware timestamps;
- staff/service assignment with tenant-local uniqueness;
- StaffAvailabilityManager application service;
- timezone-aware availability-window calculation using the staff location timezone;
- availability subtraction for recurring breaks and date-specific time off;
- booking availability RBAC permissions.

Validation rules:

- weekday must be between 0 (Sunday) and 6 (Saturday);
- start/end times must use HH:MM or HH:MM:SS;
- end time must be after start time; overnight working intervals are not supported in this slice;
- working intervals for the same staff/day cannot overlap;
- breaks must be fully contained in their working interval and cannot overlap;
- time-off intervals must have a positive duration and cannot overlap for the same staff member;
- archived staff cannot receive availability configuration;
- archived services cannot be assigned to staff.

### 7.3 Appointments

Status: **COMPLETED IN THIS SLICE**

Implemented:

- tenant appointments table with ULID identifiers;
- Customer, Staff, and Location linkage;
- UTC-aware start/end timestamps;
- explicit scheduling block timestamps including Service before/after buffers;
- appointment item service snapshots for historical name, duration, price, quantity, currency, and line total;
- appointment status history;
- appointment payment-state foundation, kept separate from Tenant Payments integration;
- AppointmentManager application service;
- transactional creation with Staff row locking before final conflict validation;
- conflict detection that ignores cancelled/no-show appointments;
- reschedule with the same availability and concurrency checks;
- cancel, confirm, complete, and no-show lifecycle transitions;
- idempotency-key support for safe request retries;
- Booking appointment RBAC permissions.

Validation and concurrency rules:

- only active staff, active services, and non-archived customers can receive appointments;
- the selected service must be assigned to the selected staff member;
- the appointment location must match the staff member's configured location;
- appointment time, including service buffers, must fit completely inside computed staff availability;
- appointments cannot cross local calendar days in this slice;
- the Staff row is locked inside the transaction before the final overlap check and allocation;
- concurrent attempts for the same staff are serialized at the staff-row boundary;
- cancelled and no-show appointments release their scheduling slot;
- appointment creation with the same idempotency key returns the existing appointment when the request payload matches;
- reusing an idempotency key for a different appointment is rejected;
- Service capacity remains stored as a service property but is not used for shared/group concurrent allocation in this slice; exclusive Staff scheduling is the authoritative conflict rule until resource/group booking is introduced.

### 7.4 Tenant Payments integration

Status: **7.4.1 + 7.4.2 COMPLETED**

Completed in 7.4.1:

- central Tenant payment-provider account registry with encrypted credentials;
- Tenant payment ledger in the Tenant database;
- Appointment-linked Tenant Payment records;
- provider selection through PaymentGatewayManager;
- company merchant account selection;
- checkout creation from immutable appointment/service snapshots;
- idempotent single-flight checkout creation;
- pending/succeeded/failed Tenant Payment lifecycle;
- Appointment payment-status synchronization;
- Tenant payment RBAC permissions;
- explicit separation from Platform Billing records.

Completed in 7.4.2:

- tenant-local webhook event ledger with idempotency and payload-hash protection;
- tenant-specific provider signature verification;
- provider transaction reconciliation;
- partial/full Tenant refunds with idempotency and over-refund protection;
- Appointment payment-state synchronization after verified success/full refund;
- no Platform Billing side effects from Tenant customer payment flows.

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
- public-data minimization;
- SEO/public route tests before indexable release.

### 7.7 Public Web & SEO Foundation

Status: **SEO-1 + SEO-2 + SEO-3 + SEO-4 COMPLETED / SEO-5 PENDING — CROSS-CUTTING**

Completed:

- SEO-1: centralized SEO metadata/value object, reusable Blade component, Platform metadata, canonical URL generation, robots.txt, sitemap.xml, and noindex middleware;
- SEO-2: Tenant public home, Tenant SEO settings, canonical primary-domain handling, Tenant robots/sitemap, and exact-host Tenant isolation;
- SEO-3: stable Service slugs, public Service listing/detail pages, Service SEO overrides, Service JSON-LD/BreadcrumbList structured data, sitemap Service entries, and permanent slug redirects;
- SEO-4: Booking entry route with explicit noindex, canonicalization to the Service page, public rate limiting, and Service-to-Booking linking.

SEO-5 remaining:

- production Search Console/domain ownership and sitemap submission;
- structured-data validation and crawl/index monitoring;
- Core Web Vitals review;
- production domain/custom-domain verification.

Full Public Booking transaction remains pending as part of Phase 7.6. That business slice will implement staff selection, availability selection, customer details, appointment creation/confirmation, and the required payment/notification integration while preserving the SEO-4 non-indexable transactional boundary.

Custom-domain canonicalization becomes active after the separate Custom Domain infrastructure delivery.

## 3. Authorization

Booking permissions are tenant-scoped through the existing RBAC team boundary.

Current permissions:

- booking.services.view
- booking.services.manage
- booking.availability.view
- booking.availability.manage
- booking.appointments.view
- booking.appointments.manage
- booking.payments.view
- booking.payments.manage

Owner/admin receive all current Booking permissions.

Manager receives all current Booking permissions except future permissions that have not yet been introduced.

Staff and viewer receive view-only Booking permissions, including booking.payments.view.

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

Phase 7 cannot be marked CLOSED until Services, Availability, Appointments, Tenant Payments, Queue, Public Booking, Public Web/SEO foundation, authorization, isolation, concurrency, documentation, and final verification are complete.
