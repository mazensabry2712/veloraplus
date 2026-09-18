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

Custom domains are not required for MVP delivery, but the system must support a domain registry.

Conceptual entity:

~~~
tenant_domains
--------------
id
tenant_id
domain
type
is_primary
status
verified_at
created_at
updated_at
~~~

Routing must resolve any verified domain to exactly one Tenant.

## 10. Domain security

A request is not accepted merely because a domain string exists in input.

The domain must be resolved from the actual request host using the trusted domain registry.

Expected controls:

- normalized hostname handling;
- duplicate prevention;
- verification state;
- exact tenant mapping;
- HTTPS in production;
- safe forwarded-host handling.

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

## 12. User switching

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
- tenant-scoped caches/state are isolated.

## 13. Customer account privacy

A customer account can be associated with multiple tenant profiles.

The central account does not grant a company access to another company's profile.

Access to a customer profile is always Customer Account + Current Tenant + Tenant Customer Profile.

## 14. Staff reuse

A staff member may participate in Booking, CRM, HR, and future modules without duplicate identities.

## 15. Tenant database creation

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

## 16. Tenant deletion

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

## 17. Isolation requirements

Automated tests must prove:

- Tenant A cannot read Tenant B business data;
- Tenant A cannot use a forged Tenant B domain;
- Tenant A cannot access Tenant B files;
- Tenant A cannot access Tenant B customer profiles;
- Tenant A cannot access Tenant B billing records where applicable;
- cached tenant state cannot cross boundaries;
- queued jobs restore the correct tenant context.

## 18. Cache and queue

Every tenant-sensitive cache key must contain tenant identity.

Every tenant-sensitive job must include enough information to restore the correct tenant context before executing.

Never assume a queue worker's current tenant from a previous job.

## 19. Tenant-aware storage

Tenant files should be namespaced by tenant.

Conceptual path:

~~~
tenants/{tenant-public-id}/...
~~~

Sensitive files remain private.

## 20. Central vs tenant data rule

Use this rule:

- If the record exists because VeloraPlus needs it for platform operation, it belongs centrally.
- If the record exists because the company conducts business, it belongs in the tenant database.
- If a record needs both contexts, keep a central registry/identifier and a tenant-specific business profile where appropriate.

Avoid duplicate shadow copies unless required for a measurable technical reason.
