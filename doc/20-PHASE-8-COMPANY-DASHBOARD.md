# VeloraPlus — Phase 8 Company Dashboard

## Status

**IN PROGRESS — 8.2 COMPANY FOUNDATION**

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

8.1 Dashboard Shell is implemented. The private `/dashboard` route, authenticated tenant-membership boundary, noindex policy, shared Dashboard layout, initial Overview screen, and access/isolation tests are in place. Local verification is green with the full test suite.

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

The existing Phase 1–7 regression suite remains green locally.

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

#### 8.2.7 Roles and permissions administration

Planned.

Only capabilities supported by the existing Core contracts should be exposed. New business capabilities require their own application services, policies, migrations, tests, and documentation.

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
