# VeloraPlus — MVP Roadmap & Acceptance Criteria

## 1. MVP definition

The first production milestone is:

VeloraPlus SaaS Platform + Booking Module.

It is not required to ship full CRM or ERP in the first release.

The MVP must prove that the Core architecture can support later Modules without rebuilding identity, tenancy, billing, or customer data.

## 2. Phase 0 — Architecture baseline

Deliver:

- database strategy;
- central/tenant boundaries;
- domain model;
- identifier strategy;
- naming conventions;
- module/feature vocabulary;
- billing vocabulary;
- security model;
- testing strategy.

Exit criteria:

- architecture docs accepted;
- no unresolved contradiction between central and tenant data ownership;
- no code built on an unapproved tenancy assumption.

## 3. Phase 1 — Platform Core / Tenancy Foundation

Build:

- tenant/company registry;
- tenant creation;
- tenant database provisioning;
- tenant routing;
- tenant domain resolution;
- memberships;
- company profile;
- staff;
- locations;
- customer account/profile model;
- core settings.

Exit criteria:

- one account can access multiple companies;
- companies use separate databases;
- no cross-tenant business access;
- switching company context is safe.

## 4. Phase 2 — Identity, Authentication & RBAC

Build:

- PlatformAccount authentication;
- registration, login/logout, password reset, email verification;
- tenant memberships;
- tenant-scoped RBAC;
- default tenant roles and permissions;
- permission-team isolation;
- membership/role synchronization.

Exit criteria:

- authenticated accounts use PlatformAccount;
- suspended accounts are rejected;
- tenant membership is required for tenant workspace access;
- roles and permissions are isolated by tenant;
- the same account can have different roles across tenants;
- default RBAC bootstrap is idempotent.

## 5. Phase 3 — Module Catalog

Build:

- Modules;
- Features;
- module-feature relations;
- dependency graph;
- Bundles;
- pricing catalog;
- catalog administration;
- module statuses.

Exit criteria:

- Module can contain multiple Features;
- Feature can be purchased individually;
- Bundle can contain Modules/Features;
- dependencies are validated;
- circular dependencies are rejected.

## 6. Phase 4 — Entitlements

Build:

- central tenant entitlement projection;
- deterministic entitlement lifecycle/source model;
- Module and Feature resolution;
- Module-to-Feature inheritance;
- transitive dependency enforcement;
- idempotent grant/revoke/scheduled-removal operations;
- projection rebuild boundary for future Billing;
- entitlement middleware;
- Blade entitlement helpers;
- entitlement-aware Policies/Services.

Exit criteria:

- active Module entitlement exposes its active Features;
- inactive/expired entitlements are denied;
- scheduled removal remains active until ends_at;
- catalog dependencies cannot be bypassed;
- Tenant A entitlements cannot authorize Tenant B;
- permissions and entitlements are separate;
- core capabilities do not require tenant entitlement rows;
- projection can be rebuilt deterministically from future Billing state;
- UI helpers never replace backend authorization.

## 7. Phase 5 — Billing

Build:

- subscription;
- subscription items;
- pricing engine;
- bundle discount;
- monthly billing;
- yearly billing;
- trial;
- upgrade;
- scheduled downgrade;
- cancellation;
- invoices;
- refunds;
- payment records;
- audit trail.

Exit criteria:

- deterministic subscription pricing;
- historical invoice amounts remain unchanged;
- a Feature can be added independently;
- verified payment activates a paid Feature;
- feature removal takes effect at period end.

## 8. Phase 6 — Payments & Provider Adapters

Build:

- provider-neutral payment contracts for Platform Billing and Tenant Payments;
- provider client/adapter;
- checkout;
- payment verification;
- webhook signature verification;
- idempotency;
- payment reconciliation;
- subscription activation;
- Kashier as the first provider adapter while preserving the same contracts for future providers.

Exit criteria:

- successful payment activates intended subscription items;
- duplicate webhooks are harmless;
- invalid signatures are rejected;
- failed payments do not grant access;
- provider references are traceable.

## 9. Phase 7 — Booking Module

Build:

### Services

- create/update/archive;
- prices;
- duration;
- buffers;
- capacity;
- booking visibility.

### Staff availability

- working hours;
- breaks;
- time off;
- staff/service assignment.

### Appointments

- create;
- view;
- reschedule;
- cancel;
- lifecycle;
- customer linkage;
- staff linkage;
- location;
- payment state.

### Queue

- waiting;
- serving;
- completed;
- skipped/no-show rules;
- business date;
- safe concurrent updates.

### Public booking

- service selection;
- staff selection where applicable;
- availability;
- time selection;
- customer details;
- confirmation;
- rate limiting.

## 10. Phase 8 — Company Dashboard

Build:

- dashboard;
- company profile;
- users;
- staff;
- customers;
- branches;
- Booking navigation;
- Billing navigation;
- Module Marketplace;
- subscription overview;
- usage overview;
- settings.

Dynamic UI:

~~~
Dashboard
  ↓
Active Modules
  ↓
Active Features
  ↓
User Permissions
~~~

## 11. Phase 9 — Customer Portal

Build:

- OTP/login flow;
- customer profile;
- appointments;
- invoices;
- payments;
- documents;
- messages where implemented.

## 12. Phase 10 — Production QA

Test categories:

### Unit
Pricing, dependency resolution, state transitions, money calculations.

### Feature
Tenant creation, switching, subscriptions, payments, Booking.

### Authorization
Roles, permissions, policies, tenant ownership.

### Isolation
Cross-tenant negative tests.

### Concurrency
Booking conflicts, queue mutations, payment/webhook races.

### Browser
Registration, company setup, module purchase, payment return, dashboard, booking.

### Database
MySQL 8.4 regression and migration correctness.

## 13. MVP critical negative tests

1. Company A cannot read Company B customers.
2. Company A cannot read Company B appointments.
3. Company A cannot access Company B files.
4. A multi-company account cannot mix tenant context.
5. Customer cannot read another customer's private history.
6. User with permission but no entitlement is denied.
7. Tenant with entitlement but user without permission is denied.
8. Expired subscription blocks paid Feature.
9. Failed payment does not activate paid Feature.
10. Invalid webhook signature is rejected.
11. Duplicate webhook does not double-activate or double-charge.
12. Feature dependency cannot be bypassed.
13. Removing a Feature does not delete its data.
14. Re-activating a Feature reuses retained business data.
15. Bundle discount is reflected correctly in invoice lines.

## 14. Definition of done

A feature is not done merely because a page works.

~~~
Business rule defined
+
Database designed
+
Authorization implemented
+
Tenant isolation verified
+
Happy path tested
+
Negative path tested
+
Concurrency considered where relevant
+
Audit/logging considered
+
UI integrated
+
Documentation updated
~~~

## 15. Future CRM phase

CRM should start after Core + Booking MVP is stable.

Recommended order:

1. Contacts / customer 360 extension.
2. Activities.
3. Tasks.
4. Leads.
5. Pipeline.
6. Deals.
7. Campaigns.
8. CRM reports.

CRM reuses Customer, Staff, Company, Locations, Files, Notifications, Billing, and Audit.

## 16. Future ERP phase

ERP follows the same Core.

Recommended order:

1. Products.
2. Categories.
3. Warehouses.
4. Inventory.
5. Suppliers.
6. Purchasing.
7. Sales.
8. Expenses.
9. Finance/accounting.

## 17. Scalability & performance gate

Before calling a high-traffic flow production-ready:

- the flow is stateless and horizontally scalable;
- database access patterns and indexes are reviewed;
- tenant-aware caching is reviewed;
- queue/offloading opportunities are reviewed;
- rate limits exist for public/high-risk endpoints;
- concurrency behavior is tested where relevant;
- p50/p95/p99 latency is measurable;
- load/stress testing is performed for critical high-traffic paths;
- observability is operational.

See `doc/09-SCALABILITY-AND-PERFORMANCE.md`.

## 18. Release gates

Do not call production ready until:

- tenant isolation passes;
- billing webhook idempotency passes;
- payment verification passes;
- subscription state machine passes;
- booking race/conflict tests pass;
- fresh MySQL environment passes;
- critical browser flows pass;
- backups/recovery are defined;
- monitoring is operational;
- deployment procedure is documented.

## 19. Implementation order

~~~
1. Foundation
2. Tenancy
3. Identity & Membership
4. Company / Staff / Customer
5. Catalog
6. Entitlements
7. Billing
8. Payments
9. Booking
10. Dashboard
11. Customer Portal
12. QA
13. Production hardening
14. CRM
15. ERP
~~~

## 20. Product promise

The user should experience VeloraPlus as one system.

Buying another Module must feel like turning on a new capability inside the same company workspace, not creating a second application or second set of customers/staff.

## 21. Final acceptance scenario

~~~
User registers
  ↓
Creates Company A
  ↓
Company A gets dedicated database
  ↓
Subdomain is assigned
  ↓
14-day trial starts
  ↓
Company selects Booking
  ↓
Company later adds a selected CRM Feature
  ↓
Pricing is calculated dynamically
  ↓
Company pays VeloraPlus
  ↓
Feature activates immediately
  ↓
Company uses shared Staff/Customers
  ↓
Customer books through public Booking
  ↓
Customer has company-specific profile/history
  ↓
Payment is recorded
  ↓
Company sees everything in one Dashboard
~~~

This validates the intended architecture.

## 22. Change control

If a future requirement conflicts with a documented rule, do not silently change implementation.

Update the relevant document, decision/changelog, schema/flow documentation, and affected tests before changing behavior.

## 23. Cross-cutting capability — Custom Domains

Custom Domains are intentionally not assigned a numbered MVP phase because they cross Tenancy, Entitlements, Billing, Networking, SSL/TLS, and the public tenant experience.

### Architectural prerequisite

Already established:

- central `tenant_domains` registry;
- tenant resolution from request host;
- exact tenant mapping;
- verified-domain requirement;
- tenant isolation boundary.

### Delivery dependency

Custom Domain implementation becomes eligible after:

1. Tenancy foundation is stable;
2. Entitlements can grant/revoke the Custom Domain Feature;
3. Billing can represent the commercial state;
4. the production edge/domain provider strategy is selected.

### Production flow

~~~
Catalog Feature
  ↓
Entitlement active
  ↓
Company adds hostname
  ↓
DNS instructions
  ↓
Ownership verification
  ↓
Traffic/routing setup
  ↓
SSL/TLS ready
  ↓
Domain active
  ↓
Request host resolves to Tenant
~~~

### Acceptance requirements

A production Custom Domain implementation must prove:

- customer-owned hostname cannot be claimed without verification;
- one hostname cannot belong to two Tenants;
- disabled/unverified hostnames do not route;
- exact host matching prevents tenant confusion;
- default VeloraPlus hostname remains usable if the custom domain fails;
- tenant database isolation remains unchanged;
- entitlement removal disables custom-domain capability without deleting tenant data;
- SSL/TLS failure does not silently mark the hostname active;
- provider failures are observable and retry-safe;
- domain onboarding and lifecycle actions are auditable.

Custom Domain details are governed by `doc/14-CUSTOM-DOMAIN-ARCHITECTURE.md`.
