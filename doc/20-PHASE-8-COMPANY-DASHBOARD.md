# VeloraPlus — Phase 8 Company Dashboard

## Status

**IN PROGRESS — 8.4.4 SUBSCRIPTION CHANGES**

Phase 8 is the next major product delivery after the completed Phase 7 Booking implementation. It converts the verified Core + Booking backend capabilities into the authenticated Company Dashboard used by tenant members.

This phase must build on the existing Tenancy, Identity/RBAC, Entitlements, Billing, Payments, Booking, and public-web boundaries. It must not duplicate business rules or create a second authorization system.

## 1. Objective

Deliver the authenticated Company Dashboard for a Tenant so company members can operate the capabilities already implemented in the Core and Booking module.

Phase 8 is primarily a presentation/application-layer delivery. Existing domain/application services remain authoritative for business behavior.

## 2. Route and context boundary

Dashboard requests are private tenant workspace requests.

Required request flow:

    authenticated Platform Account
        ↓
    active Tenant Membership
        ↓
    current Tenant context
        ↓
    entitlement check where applicable
        ↓
    tenant-scoped permission / Policy
        ↓
    Controller
        ↓
    existing Application service
        ↓
    Blade view

Rules:

- Dashboard routes must never trust a client-supplied tenant_id.
- Tenant membership and tenant context must be established before tenant business queries.
- Entitlements and permissions remain backend-authoritative.
- Policies and application services remain the final business/security boundaries.
- Private Dashboard pages must use the explicit noindex policy and must never become public SEO content.
- Tenant data must remain inside the current Tenant database boundary.

## 3. Initial navigation

The Dashboard navigation is organized around capabilities already available in the product:

### Overview

- Dashboard home
- Company summary
- Operational booking summary
- Recent activity where supported by existing data

### Company

- Company Profile
- Locations / Branches
- Staff
- Customers
- Users
- Roles & Permissions

### Booking

- Services
- Availability
- Appointments
- Queue
- Payments

### Billing

- Subscription
- Invoices
- Payments
- Credits / Refund history where the current commercial model exposes them

### Platform

- Module Marketplace
- Feature/entitlement state
- Usage / limits
- Settings

Navigation visibility may improve the UX, but it must never be the authorization mechanism.

## 4. Phase 8 delivery slices

### 8.1 Dashboard shell

### Current implementation status

8.1 Dashboard Shell is implemented. The private `/dashboard` route, authenticated tenant-membership boundary, noindex policy, shared Dashboard layout, initial Overview screen, and access/isolation tests are in place. Local verification was green before the current Phase 8 additions.

- authenticated tenant layout;
- sidebar / primary navigation;
- tenant/company context display;
- account menu;
- responsive layout;
- private noindex boundary;
- shared flash/error/validation presentation;
- permission-aware navigation helpers.

### 8.2 Company foundation

Company Foundation is now in progress.

#### 8.2.1 Company Profile — Backend implemented

The Company Profile backend uses the existing Core tenancy model and tenant-scoped RBAC.

Source of truth:

- `tenants` in the central platform database is authoritative for company profile fields used by routing, localization, currency, and platform context.
- `company_settings` in the tenant database is a synchronized tenant-local projection for `company.*` settings already used by tenant features such as SEO.

Implemented fields:

- `name`;
- `legal_name`;
- `industry`;
- `country_code`;
- `default_currency`;
- `timezone`;
- `locale`.

Authorization:

- active tenant membership is required;
- tenant-scoped `company.update` is required;
- `TenantPolicy` verifies that the policy target is the current tenant context;
- the existing RBAC system is reused.

Backend components:

- `UpdateCompanyProfileRequest` validates and normalizes input;
- `CompanyProfileManager` owns profile persistence and projection synchronization;
- `CompanyProfileController` is the HTTP boundary;
- `POST /dashboard/company/profile` is the update route;
- `CompanyProfileTest` covers successful updates, RBAC denial, validation failure, and projection synchronization.

The existing Phase 1–7 regression suite was green before the current Phase 8 additions; final full-suite verification for the current batch is pending.

#### 8.2.2 Locations / Branches — Backend implemented

The Locations backend uses the existing tenant-local `locations` table and current tenant context.

Implemented operations:

- create;
- update;
- archive via soft delete with explicit `inactive` state.

Authorization and isolation:

- `LocationPolicy` reuses `locations.view/manage`;
- location route model binding runs inside the current tenant database;
- foreign-tenant location IDs are not addressable from another tenant host;
- inactive locations cannot be assigned through the create/update request contract.

Backend components:

- `LocationManager`;
- `StoreLocationRequest`;
- `UpdateLocationRequest`;
- `LocationPolicy`;
- `LocationController`;
- `LocationManagementTest`.

The test suite covers CRUD lifecycle, RBAC denial, input validation, and cross-tenant address isolation.

#### 8.2.3 Tenant Settings — Backend implemented

The tenant settings backend uses the existing tenant-local `company_settings` key/value foundation. Arbitrary keys are not accepted.

The first supported settings namespace is `seo.*`, matching the existing public-web SEO contract:

- `seo.site_title`;
- `seo.site_description`;
- `seo.default_og_image`;
- `seo.robots`;
- `seo.locale`.

Backend components:

- `TenantSettingsManager` reads and updates the supported settings;
- `UpdateTenantSettingsRequest` validates and normalizes the supported SEO settings;
- `CompanySettingPolicy` enforces tenant-context + `settings.view/manage` permissions;
- `TenantSettingsController` is the HTTP boundary;
- `PUT /dashboard/company/settings` is the update route;
- `TenantSettingsTest` covers authorized updates, RBAC denial, malformed/unknown settings, and tenant isolation.

The application service also enforces the setting allowlist so non-HTTP callers cannot write arbitrary tenant settings.

#### 8.2.4 Staff — Backend implemented

The Staff backend manages the tenant's business Staff identity without creating a second account/role system.

Implemented fields:

- `name`;
- `phone`;
- `email`;
- `location_id`;
- `status`.

Implemented operations:

- create;
- update;
- archive via soft delete with explicit `inactive` state.

Rules:

- staff records remain tenant-local;
- assigned locations must belong to the current tenant and be active;
- an existing `account_id` association is preserved during profile updates;
- account linking and membership/role administration remain in the later Users/Memberships slice;
- existing Staff Availability and Booking logic continue to consume the same Staff entity.

Backend components:

- `StaffManager`;
- `StoreStaffRequest`;
- `UpdateStaffRequest`;
- `StaffPolicy`;
- `StaffController`;
- `StaffManagementTest`.

The Staff test suite covers CRUD lifecycle, RBAC denial, inactive-location validation, and cross-tenant location assignment protection.

#### 8.2.5 Customers — Backend implemented

The Customer backend manages company-specific Customer Profiles inside the current tenant database.

Implemented fields:

- `name`;
- `phone`;
- `email`;
- `status`;
- `source`;
- `notes`.

Implemented operations:

- create;
- update;
- archive via soft delete with explicit `inactive` state.

Rules:

- Customer Profiles remain tenant-local;
- `customer_account_id` is preserved during profile updates and remains a future account/portal integration point;
- no cross-tenant Customer Profile can be addressed through another tenant host;
- Customer management reuses the existing `customers.view/manage` permissions.

Backend components:

- `CustomerManager`;
- `StoreCustomerRequest`;
- `UpdateCustomerRequest`;
- `CustomerPolicy`;
- `CustomerController`;
- `CustomerManagementTest`.

The test suite covers CRUD lifecycle, RBAC denial, input validation, and cross-tenant record isolation.

#### 8.2.6 Users and Memberships — Backend implemented

The tenant users surface reuses the existing identity model:

- `PlatformAccount` remains central and reusable across companies;
- `TenantMembership` remains the tenant access record;
- Spatie tenant-scoped roles remain the permission source;
- no new User model or authentication system is introduced.

Implemented operations:

- add an existing Platform Account to the current Tenant by email;
- assign an existing tenant role;
- change a membership role;
- activate/deactivate a membership;
- synchronize the Spatie role assignment with membership state.

Security/invariants:

- only active Platform Accounts can receive an active membership;
- selected roles must belong to the current Tenant;
- membership mutations require `members.manage`;
- a membership from another Tenant cannot be mutated through the current Tenant context;
- the current Tenant must retain at least one active `owner`;
- deactivation removes the member's tenant-scoped Spatie roles without affecting roles in other Tenants;
- account creation/invitations and account-to-Staff linking remain separate concerns for later slices.

Backend components:

- `TenantMembershipManager`;
- `StoreTenantMembershipRequest`;
- `UpdateTenantMembershipRequest`;
- `TenantMembershipPolicy`;
- `TenantMembershipController`;
- `TenantMembershipManagementTest`.

Routes:

- `POST /dashboard/company/users`;
- `PATCH /dashboard/company/users/{membership}`;
- `DELETE /dashboard/company/users/{membership}`.

The test suite covers member addition, role changes, deactivation, permission denial, last-owner protection, and cross-tenant membership isolation.

#### 8.2.7 Roles and Permissions Administration — Backend implemented

The tenant role administration surface reuses the existing Spatie Teams RBAC model.

Implemented operations:

- create custom tenant roles;
- assign existing platform permissions to a custom role;
- update a custom role's permissions;
- delete an unused custom role.

Rules:

- permissions remain platform-defined and central; the Dashboard cannot invent new permission names;
- role records remain tenant-scoped through `tenant_id`;
- the five bootstrap/system roles (`owner`, `admin`, `manager`, `staff`, `viewer`) are reserved and cannot be modified/deleted from the Dashboard;
- a role referenced by any Tenant Membership cannot be deleted;
- role changes are synchronized through the existing Spatie permission team context;
- a role from another Tenant cannot be mutated through the current Tenant context.

Backend components:

- `TenantRoleManager`;
- `StoreTenantRoleRequest`;
- `UpdateTenantRoleRequest`;
- `TenantRolePolicy`;
- `TenantRoleController`;
- `TenantRoleManagementTest`.

Routes:

- `POST /dashboard/company/roles`;
- `PATCH /dashboard/company/roles/{role}`;
- `DELETE /dashboard/company/roles/{role}`.

The test suite covers custom role creation/update, system-role protection, deletion protection for referenced roles, RBAC denial, and cross-tenant isolation.

### 8.3 Booking workspace

The Dashboard Booking workspace consumes the already-verified Booking application services and policies. It does not duplicate Booking business rules.

#### 8.3.1 Booking Services — Backend implemented

The Dashboard Service mutation boundary is implemented on top of the existing `ServiceManager` and `ServicePolicy`.

Supported operations:

- create service;
- update service;
- archive service.

The existing ServiceManager remains authoritative for:

- stable public slugs and slug-history redirects;
- duration and buffer rules;
- integer money values and currency normalization;
- deposit bounds;
- capacity;
- lifecycle state;
- online-booking visibility;
- service SEO fields.

Security boundary:

- active Tenant Membership is required;
- `booking.services` entitlement is required;
- `booking.services.manage` permission is required for mutations;
- tenant-aware route model binding runs after tenant context initialization.

Backend components:

- `StoreBookingServiceRequest`;
- `UpdateBookingServiceRequest`;
- `BookingServiceController`;
- existing `ServiceManager`;
- existing `ServicePolicy`;
- `BookingServiceManagementTest`.

Routes:

- `POST /dashboard/booking/services`;
- `PATCH /dashboard/booking/services/{service}`;
- `DELETE /dashboard/booking/services/{service}`.

Tests cover create/update/archive, permission denial, entitlement denial, validation, and cross-tenant route-binding isolation.

#### 8.3.2 Staff Availability — Backend implemented

The Dashboard Availability boundary consumes the existing `StaffAvailabilityManager`.

Supported operations:

- assign/unassign an active Booking Service to Staff;
- create/update/delete recurring working hours;
- create/update/delete working-hour breaks;
- create/update/delete Staff Time Off.

Authorization and entitlement:

- active Tenant Membership is required;
- availability mutations require `booking.availability`;
- Service assignment additionally requires `booking.services`;
- `booking.availability.manage` is enforced through the existing Staff policy boundary;
- nested tenant records are checked against the selected Staff/Working Hour relationships.

Time handling:

- recurring working hours and breaks use local `HH:MM` / `HH:MM:SS` values;
- Time Off accepts timezone-aware ISO-8601 timestamps and the existing manager normalizes them to UTC;
- overlapping hours, overlapping breaks, out-of-range breaks, and overlapping time off remain governed by `StaffAvailabilityManager`.

Backend components:

- six Dashboard availability request classes for working hours, breaks, and time off;
- `BookingAvailabilityController`;
- existing `StaffAvailabilityManager`;
- `StaffPolicy::manageAvailability`;
- `BookingAvailabilityManagementTest`.

Routes include Staff/Service assignment plus nested Working Hours, Breaks, and Time Off mutations under `/dashboard/booking/availability`.

Tests cover successful lifecycle operations, permission denial, entitlement denial, validation/ownership boundaries, and required dual entitlement for Service assignment.

#### 8.3.3 Appointments — Backend implemented

The Dashboard Appointment boundary consumes the existing `AppointmentManager`.

Supported operations:

- create appointment;
- reschedule pending/confirmed appointment;
- confirm pending appointment;
- complete confirmed appointment;
- cancel appointment with reason;
- mark no-show.

Request boundaries validate tenant-local Customer/Staff/Service identifiers and appointment input before the application service is invoked.

The existing AppointmentManager remains authoritative for:

- Staff availability;
- service assignment;
- local-time/date behavior;
- buffer-aware conflict detection;
- concurrency locking;
- idempotency;
- service/location/customer linkage;
- appointment status transitions;
- status history;
- payment-state foundation.

Security and entitlement:

- active Tenant Membership is required;
- `booking.appointments` entitlement is required;
- `booking.appointments.manage` is enforced through the existing Appointment policy;
- tenant-aware model binding is guaranteed by the centralized tenant-before-bind middleware priority.

Backend components:

- `StoreAppointmentRequest`;
- `RescheduleAppointmentRequest`;
- `CancelAppointmentRequest`;
- `AppointmentController`;
- existing `AppointmentManager`;
- existing `AppointmentPolicy`;
- `AppointmentManagementTest`.

Routes:

- `POST /dashboard/booking/appointments`;
- `PATCH /dashboard/booking/appointments/{appointment}`;
- `POST /dashboard/booking/appointments/{appointment}/confirm`;
- `POST /dashboard/booking/appointments/{appointment}/complete`;
- `POST /dashboard/booking/appointments/{appointment}/cancel`;
- `POST /dashboard/booking/appointments/{appointment}/no-show`.

Tests cover create/reschedule/complete, permission denial, entitlement denial, validation, lifecycle transition enforcement, and cross-tenant route binding isolation.

#### 8.3.4 Queue — Backend implemented

The Dashboard Queue boundary consumes the existing `QueueManager`.

Supported operations:

- create a daily Queue by Location + Service + business date;
- open/close a Queue;
- enqueue active Customers;
- optionally link a Queue Entry to an Appointment;
- call the next waiting entry;
- complete, skip, or mark a Queue Entry as no-show.

The existing QueueManager remains authoritative for:

- one queue per Location + Service + business date;
- monotonic queue positions;
- queue state transitions;
- one serving entry at a time;
- queue/appointment customer-service-location-date matching;
- idempotent enqueue behavior;
- concurrency locking.

Security and entitlement:

- active Tenant Membership is required;
- `booking.queues` entitlement is required;
- `booking.queues.manage` is enforced through the existing Queue policy;
- nested Queue Entry actions verify that the entry belongs to the addressed Queue.

Backend components:

- `StoreQueueRequest`;
- `EnqueueQueueEntryRequest`;
- `QueueEntryReasonRequest`;
- `QueueController`;
- existing `QueueManager`;
- existing `QueuePolicy`;
- `QueueManagementTest`.

Routes are under `/dashboard/booking/queues` for Queue lifecycle and Queue Entry operations.

Tests cover queue lifecycle, entry processing, permission/entitlement boundaries, resource validation, nested-record ownership, and existing QueueManager behavior.

#### 8.3.5 Tenant Payments — Backend implemented

The Dashboard Tenant Payments boundary consumes the existing provider-neutral `TenantPaymentManager` and keeps Customer → Company payments separate from Platform Billing.

Supported operations:

- create a Tenant payment checkout for an eligible Appointment;
- reconcile a pending Tenant Payment through the configured provider lookup capability;
- request a partial or full refund for a succeeded Tenant Payment.

Security and entitlement:

- active Tenant Membership is required;
- `booking.payments` entitlement is required;
- `booking.payments.manage` is required for the exposed mutations;
- route model binding resolves `Appointment` and `TenantPayment` inside the current Tenant context;
- encrypted merchant credentials remain centrally stored and are never returned by the Dashboard API boundary.

State integrity:

- Dashboard cannot mark a payment succeeded directly;
- successful payment state is still established by the existing verified webhook/reconciliation path;
- refund amounts remain bounded by the remaining refundable payment amount;
- idempotent checkout/refund behavior remains owned by `TenantPaymentManager`;
- full refund transitions the Tenant Payment and linked Appointment payment state through the existing manager;
- no Platform Invoice or Platform Payment records are created by customer Booking payment actions.

Backend components:

- `RefundTenantPaymentRequest`;
- `TenantPaymentController`;
- existing `TenantPaymentManager`;
- existing `TenantPaymentPolicy`;
- expanded `FakeTenantPaymentGateway` test adapter;
- `TenantPaymentManagementTest`.

Routes:

- `POST /dashboard/booking/payments/appointments/{appointment}`;
- `POST /dashboard/booking/payments/{payment}/reconcile`;
- `POST /dashboard/booking/payments/{payment}/refund`.

The test suite covers checkout creation, checkout URL propagation, permission/entitlement boundaries, reconciliation, full refund, validation, and separation from Platform Billing.

### 8.4 Billing workspace

The Dashboard Billing workspace is strictly for Company → VeloraPlus Platform Billing. Customer → Company Booking payments remain under the Tenant Payments boundary in 8.3.5.

#### 8.4.1 Subscription & Billing access — Backend implemented and verified

Implemented backend boundary:

- `billing.view` and `billing.manage` tenant-scoped permissions;
- `PlatformBillingPolicy` for Subscription, Invoice, Payment, and Refund tenant ownership checks;
- `PlatformBillingDashboardService::overview()` for current subscription, recent invoices, and recent platform payments;
- `PlatformBillingDashboardService::cancelAtPeriodEnd()` delegating to the existing `SubscriptionService`;
- `CancelSubscriptionRequest`;
- `PlatformBillingController`;
- subscription cancellation route:
  `POST /dashboard/billing/subscription/{subscription}/cancel`;
- RBAC coverage for Owner/Admin/Manager/Viewer/Staff billing access.

Existing Billing services remain the financial source of truth. Dashboard code does not write invoices, payments, refunds, credits, or subscription totals directly.

#### 8.4.2 Platform Billing Checkout — Backend implemented and verified

Implemented:

- create a pending Platform Payment from an open invoice;
- create a hosted checkout session through the configured Platform Payment Gateway;
- persist checkout/session/provider metadata including the checkout URL;
- reuse an existing pending checkout session to avoid duplicate sessions;
- pass the authenticated company billing contact to the provider;
- keep payment success authoritative through the existing verified webhook/reconciliation path.

Route:

- `POST /dashboard/billing/invoices/{invoice}/checkout`

The checkout slice was verified locally at 213 tests / 1099 assertions and the corresponding GitHub Actions run was green.

#### 8.4.3 Invoices, payments, refunds, and credits — Backend implemented and verified

Implemented:

- Billing overview now includes recent Platform Invoices, Platform Payments, Platform Refunds, and Platform Credits;
- open invoice voiding through `InvoiceService`;
- partial/full Platform Payment refunds through the provider-neutral `RefundGateway`;
- refund amount protection remains inside `RefundService`;
- Platform Refund idempotency keys with a database uniqueness constraint;
- explicit refund failure lifecycle and billing audit events;
- initiating Platform Account recorded on Dashboard refunds;
- Platform Credit issuance through `CreditService`;
- tenant-safe Policies for billing financial records;
- all mutations remain behind authenticated tenant membership, `billing.manage`, and `noindex`.

Routes:

- `POST /dashboard/billing/invoices/{invoice}/void`
- `POST /dashboard/billing/payments/{payment}/refund`
- `POST /dashboard/billing/credits`

Dashboard Billing remains strictly Company → VeloraPlus. Tenant customer Booking payments remain under 8.3.5 and never create Platform Billing records.

The 8.4.3 backend slice is implemented and locally verified. The dedicated Platform Billing/Billing tests pass at 26 tests / 110 assertions, and the full local regression suite is green at 218 tests / 1129 assertions.

#### 8.4.4 Subscription changes

Planned.

### 8.5 Module Marketplace

Build the tenant-facing catalog surface on top of the existing:

- Modules;
- Features;
- Bundles;
- Prices;
- Entitlements;
- subscription flow.

The UI must distinguish:

- available but not owned;
- active;
- scheduled for removal;
- unavailable because of dependencies or commercial state.

Purchasing/activation must require the established Billing + Payment + Entitlement flow.

### 8.6 Usage and settings

Provide authorized views for:

- entitlement limits;
- tenant configuration;
- localization/timezone/currency presentation;
- integration settings that already have a backend contract.

Future settings that need new business logic remain out of this slice.

### 8.3 Booking workspace

Use the existing Booking application services and policies for:

- Services;
- Staff Availability;
- Appointments;
- Queue;
- Tenant Payments.

The Dashboard must consume the existing Tenant Payment boundary for customer payments. It must not route customer Booking payments through Platform Billing.

### 8.4 Billing workspace

Expose the existing Platform Billing domain to authorized tenant members:

- current subscription;
- subscription items;
- pricing context;
- invoices;
- payment state;
- refunds / credits where appropriate.

Billing UI must never rewrite financial state directly. State changes go through the existing Billing application services.

### 8.5 Module Marketplace

Build the tenant-facing catalog surface on top of the existing:

- Modules;
- Features;
- Bundles;
- Prices;
- Entitlements;
- subscription flow.

The UI must distinguish:

- available but not owned;
- active;
- scheduled for removal;
- unavailable because of dependencies or commercial state.

Purchasing/activation must require the established Billing + Payment + Entitlement flow.

### 8.6 Usage and settings

Provide authorized views for:

- entitlement limits;
- tenant configuration;
- localization/timezone/currency presentation;
- integration settings that already have a backend contract.

Future settings that need new business logic remain out of this slice.

## 5. Authorization matrix

Phase 8 must reuse the existing tenant-scoped RBAC model.

Examples:

- company profile: tenant management permissions;
- staff: staff view/manage permissions;
- customers: customer view/manage permissions;
- booking services: `booking.services.view/manage`;
- availability: `booking.availability.view/manage`;
- appointments: `booking.appointments.view/manage`;
- payments: `booking.payments.view/manage`;
- queue: `booking.queues.view/manage`;
- billing and marketplace: their existing central permission/entitlement contracts.

No new role system is introduced.

## 6. Entitlement boundary

Dashboard navigation and pages may be hidden when the Tenant does not own a capability, but backend enforcement remains mandatory.

For Booking, the effective rule remains:

    Tenant context
        ↓
    Membership
        ↓
    Booking entitlement
        ↓
    Booking permission / Policy
        ↓
    Application service

For commercial Marketplace actions, the effective flow must remain:

    Catalog
        ↓
    Billing
        ↓
    Verified payment
        ↓
    Entitlement projection
        ↓
    Module / Feature access

## 7. UI architecture

The Dashboard will use the repository's locked frontend approach:

- Blade;
- Tailwind CSS;
- Alpine.js only where interaction needs it;
- Vanilla JavaScript for small progressive enhancements;
- Vite for assets.

No new frontend framework is introduced for Phase 8.

The Dashboard should use shared Blade components/layouts instead of duplicating page markup.

The visual system, typography, spacing, component states, and color tokens should be documented before broad page implementation and then reused consistently across the Dashboard.

## 8. Data and performance rules

- Do not load unrelated tenant datasets on every Dashboard request.
- Prefer paginated listings for Customers, Staff, Users, Appointments, Services, and Queue Entries.
- Use aggregate queries for summary cards instead of loading complete collections.
- Keep tenant-aware cache keys where caching is introduced.
- Never cache authorization decisions without a clear invalidation strategy.
- High-traffic public paths remain separate from private Dashboard traffic.
- Dashboard pages must not leak provider credentials, customer payment secrets, or private webhook payloads.

## 9. Testing requirements

Each Phase 8 slice must include:

- happy-path feature tests;
- invalid-input tests;
- authorization tests;
- tenant-isolation tests;
- entitlement tests;
- policy/service integration coverage where behavior changes;
- browser coverage for critical user flows once browser tooling is part of the release setup;
- regression coverage for existing Booking/Billing/Payment behavior.

Critical negative tests include:

1. A user without tenant membership cannot enter the Dashboard.
2. A user cannot view another Tenant's records by changing URL parameters.
3. A user with permission but without entitlement cannot use a commercial Module capability.
4. A Tenant with an entitlement cannot bypass missing user permission.
5. Private Dashboard pages never become indexable public pages.
6. Dashboard actions cannot create Platform Billing records for Tenant customer payments.

## 10. Acceptance criteria

Phase 8 is complete when:

- authenticated tenant members can enter the Company Dashboard;
- tenant context and membership isolation are enforced;
- navigation is permission/entitlement aware;
- Company, People, Booking, Billing, Marketplace, and Settings surfaces expose the implemented backend capabilities;
- no Dashboard action bypasses application services or policies;
- existing Phase 1–7 tests remain green;
- new Dashboard tests cover authorization and tenant isolation;
- the private Dashboard remains outside the public SEO surface;
- documentation is synchronized with the delivered slices.

## 11. Explicitly deferred from Phase 8

The following are not part of the initial Dashboard delivery unless separately approved:

- Customer Portal;
- CRM screens;
- ERP screens;
- Custom Domain infrastructure;
- production SEO operations (SEO-5);
- multi-region infrastructure;
- a new frontend framework;
- direct database writes from Blade/JavaScript.

## 12. Implementation order

1. 8.1 Dashboard shell
2. 8.2 Company foundation
3. 8.3 Booking workspace
4. 8.4 Billing workspace
5. 8.5 Module Marketplace
6. 8.6 Usage and settings
7. Phase 8 verification and documentation sync

## 13. Phase completion rule

Phase 8 is a delivery phase, not a redesign of the Core.

If a required Dashboard feature exposes a missing backend capability, the correct response is to add that capability through the existing domain/application architecture and tests before wiring the UI. Do not add business logic directly inside Blade templates or controllers merely to make a screen work.
