# VeloraPlus — SaaS, Tenancy & Identity Architecture

## 1. Tenancy model

VeloraPlus uses one central/control database plus one dedicated database per Company.

~~~
Central DB
    │
    ├── Platform Accounts
    ├── Tenant Registry
    ├── Domains
    ├── Memberships
    ├── Catalog
    ├── Pricing
    ├── Subscriptions
    ├── Platform Invoices
    └── Platform Payments
    │
    ├───────────────┬───────────────┐
    ↓               ↓               ↓
Company DB A     Company DB B    Company DB C
~~~

This is the canonical tenancy decision.

## 2. Why separate tenant databases

Each company gets its own database to provide strong data isolation and clear operational boundaries.

Benefits:

- reduced accidental cross-tenant reads;
- cleaner per-company backup/restore;
- easier company export/archive;
- clearer future data residency/retention options;
- independent tenant operations.

## 3. Central database responsibilities

Recommended central entities:

~~~
platform_accounts
tenants
tenant_domains
tenant_memberships
platform_modules
platform_features
module_features
feature_dependencies
catalog_prices
bundles
bundle_items
subscriptions
subscription_items
platform_invoices
platform_invoice_items
platform_payments
payment_provider_accounts
webhook_events
platform_settings
~~~

## 4. Tenant database responsibilities

Recommended tenant entities for MVP/Core:

~~~
company_settings
staff
customers
locations
services
files/media metadata
notifications
tenant activities
~~~

Booking adds:

~~~
staff_working_hours
staff_breaks
staff_time_off
appointments
appointment_items
resources
resource_service
queues
queue_entries
appointment_status_histories
booking_settings
~~~

CRM and ERP later add their own domain tables.

## 5. No cross-database foreign keys

Never create database-enforced foreign keys from a tenant database into the central database.

Cross-context references should use stable opaque identifiers such as UUID or ULID-style IDs.

The relationship is enforced by application/domain logic.

## 6. Identity model

The platform distinguishes:

- Platform Account;
- Tenant;
- Membership;
- Staff;
- Customer Account;
- Tenant Customer Profile.

### Platform Account

Represents the login identity.

### Tenant

Represents the company/business.

### Membership

Connects an Account to a Tenant with a role/context.

### Staff

Represents the person's operational/business identity inside a tenant.

### Customer Account

Optional platform identity for customers who use the customer portal.

### Tenant Customer Profile

Company-specific customer relationship and history.

## 7. Tenant context

Every request entering company application space must establish one current Tenant context before accessing tenant-owned business data.

Conceptually:

~~~
Host
  ↓
Tenant Resolver
  ↓
Tenant Registry
  ↓
Tenant Database Selection
  ↓
Authenticated Account
  ↓
Membership
  ↓
Authorization
  ↓
Business Request
~~~

## 8. Default domain

Primary routing model:

~~~
{slug}.velora.com
~~~

The slug must be unique among active tenant domains.

## 9. Custom domains

Custom domains are architecturally supported but are not required to be enabled for the first MVP release.

The central domain registry is the source of truth for mapping hostnames to Tenants.

Conceptual relationship:

~~~
Tenant
  │
  ├── default platform hostname
  │      {slug}.velora.com
  │
  └── custom hostnames
         app.customer-domain.com
         booking.customer-domain.com
~~~

The customer owns the domain registration. VeloraPlus does not provision the customer's registrar account; it provides DNS instructions, verifies control, and manages the platform-side lifecycle.

Custom Domain is a catalog Feature. Its commercial availability is controlled by Entitlement; price/inclusion is catalog configuration.

## 10. Custom domain onboarding and lifecycle

Expected lifecycle:

~~~
pending
  ↓
verifying
  ↓
provisioning
  ↓
active
  ↓
disabled / failed
~~~

A custom domain must not become routable until server-side verification succeeds.

Verification and SSL state are separate concerns:

~~~
Ownership verification
+
Traffic/routing configuration
+
SSL/TLS readiness
=
Domain active
~~~

The selected edge/domain provider is an infrastructure decision and must remain behind an internal application boundary.

## 11. Domain security

A request is not accepted merely because a domain string exists in input.

The domain must be resolved from the actual trusted request host using the central domain registry.

Required controls:

- normalized/canonical hostname storage;
- lowercase and scheme/path/port rejection during input normalization;
- duplicate hostname prevention;
- exact hostname matching;
- explicit verification state;
- tenant mapping to exactly one Tenant;
- rejection of reserved VeloraPlus platform hostnames;
- HTTPS in production;
- safe forwarded-host handling;
- no routing based on a user-supplied tenant_id;
- no activation from a browser redirect alone.

Detailed domain security, DNS, SSL, and provider boundaries are defined in doc/14-CUSTOM-DOMAIN-ARCHITECTURE.md.

## 11. Membership

Membership is a central concept because one account can belong to multiple companies.

It should contain:

- account identifier;
- tenant identifier;
- role context;
- status;
- joined_at;
- invitation metadata where needed.

A membership is not the same thing as a tenant Staff profile.

For tenant workspace routes, an authenticated Platform Account must have an active Membership for the current Tenant. Tenant context resolution and membership authorization are separate controls; public tenant routes such as public Booking can use tenant context without requiring an account membership.

## 12. Authentication and RBAC

VeloraPlus authentication is platform-level and tenant authorization is tenant-scoped.

### Authentication boundary

- The authenticated model is `PlatformAccount` on the central database.
- Fortify provides registration, login/logout, password reset, and email verification in the initial implementation.
- Authentication state and password-reset/session records remain central.
- Suspended Platform Accounts must not authenticate.

### RBAC boundary

Spatie Laravel Permission runs with Teams enabled. The configured team key is `tenant_id` and the permission tables remain in the central database.

Roles are tenant-specific. Permissions are global definitions that are assigned to tenant-scoped roles.

The authorization sequence is:

~~~
Tenant Context
    ↓
Active Membership
    ↓
Permission Team = Tenant ID
    ↓
Role / Permission Check
    ↓
Business Authorization
~~~

`tenant_memberships` answers workspace membership; Spatie roles and permissions answer capabilities inside that workspace.

Every permission-sensitive request must establish the current tenant permission team before calling `can()`, `hasRole()`, Policies, or package middleware. The team value must be cleared in a `finally` block after request processing.

### Tenant-specific role isolation

The same Platform Account may have different roles in different tenants. A role assignment in Tenant A must never authorize a request in Tenant B.

Role and permission pivot identifiers use ULIDs to match the platform identity policy. Tenant IDs are central ULIDs and are used directly as the team key.

The package's default global-role capability is not used for company workspace roles. VeloraPlus creates tenant-scoped roles only for the standard workspace roles and permits custom tenant roles later through the platform's role-management flow.

## 13. User switching

A multi-company user needs an explicit company switcher.

~~~
Login
  ↓
Choose / remember active company
  ↓
Enter tenant context
  ↓
Dashboard
~~~

When switching:

- current tenant context changes;
- tenant database connection changes;
- permissions are recalculated;
- entitlements are recalculated;
- tenant-scoped caches/state are isolated;
- domain-derived tenant context is resolved again on the new request.

## 14. Customer account privacy

A customer account can be associated with multiple tenant profiles.

The central account does not grant a company access to another company's profile.

Access to a customer profile is always Customer Account + Current Tenant + Tenant Customer Profile.

## 15. Staff reuse

A staff member may participate in Booking, CRM, HR, and future modules without duplicate identities.

## 16. Tenant database creation

New tenant lifecycle:

~~~
Register Platform Account
  ↓
Create Tenant Registry Record
  ↓
Allocate Tenant Database
  ↓
Run Tenant Migrations
  ↓
Create Tenant Baseline Data
  ↓
Create Membership
  ↓
Create Trial / Subscription
  ↓
Route to tenant domain
~~~

Database provisioning must be idempotent and recoverable.

## 17. Tenant deletion

Tenant deletion is a lifecycle, not an immediate DROP.

Preferred lifecycle:

~~~
Active
  ↓
Cancellation requested
  ↓
Grace / expiry
  ↓
Archived
  ↓
Retention
  ↓
Permanent purge
~~~

A permanent purge must be explicit and audited.

## 18. Isolation requirements

Automated tests must prove:

- Tenant A cannot read Tenant B business data;
- Tenant A cannot use a forged Tenant B domain;
- Tenant A cannot access Tenant B files;
- Tenant A cannot access Tenant B customer profiles;
- Tenant A cannot access Tenant B billing records where applicable;
- cached tenant state cannot cross boundaries;
- queued jobs restore the correct tenant context.

## 19. Cache and queue

Every tenant-sensitive cache key must contain tenant identity.

Every tenant-sensitive job must include enough information to restore the correct tenant context before executing.

Never assume a queue worker's current tenant from a previous job.

## 20. Tenant-aware storage

Tenant files should be namespaced by tenant.

Conceptual path:

~~~
tenants/{tenant-public-id}/...
~~~

Sensitive files remain private.

## 21. Central vs tenant data rule

Use this rule:

- If the record exists because VeloraPlus needs it for platform operation, it belongs centrally.
- If the record exists because the company conducts business, it belongs in the tenant database.
- If a record needs both contexts, keep a central registry/identifier and a tenant-specific business profile where appropriate.

Avoid duplicate shadow copies unless required for a measurable technical reason.
