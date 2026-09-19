# VeloraPlus — Phase 4 Entitlements

## Status

**IMPLEMENTED — awaiting local verification gate.**

Phase 4 establishes the tenant entitlement layer between the central commercial catalog and future Billing. It provides a deterministic tenant-scoped projection and reusable backend/UI enforcement without introducing subscriptions or payment processing.

## 1. Scope

Implemented:

- central `tenant_entitlements` projection;
- entitlement lifecycle states;
- entitlement source precedence;
- module and feature access resolution;
- module-to-feature inheritance;
- dependency-aware access checks;
- dependency projection during grants;
- idempotent grant/revoke/schedule operations;
- rebuildable projection API for future Billing;
- centralized entitlement middleware;
- Blade entitlement helpers;
- tenant isolation tests.

Not implemented in this phase:

- subscriptions;
- subscription items;
- invoices;
- payment processing;
- Kashier webhooks;
- commercial checkout;
- customer-facing Module Marketplace pages.

Those remain later phases.

## 2. Source of truth boundary

Phase 4 does not make `tenant_entitlements` the financial source of truth.

The intended future flow is:

~~~
Subscription + Subscription Items
            ↓
      Entitlement projection
            ↓
tenant_entitlements
            ↓
authorization checks
~~~

`tenant_entitlements` is a fast tenant-level authorization projection. It must be rebuildable from future Billing state.

During Phase 4, `grant()` and `rebuildProjection()` are the application bridge used by tests and future callers. Billing will become the authoritative producer in Phase 5.

## 3. Data model

Central table:

~~~
tenant_entitlements
------------------
id
tenant_id
catalog_type
catalog_key
status
source
source_reference
quantity
starts_at
ends_at
metadata
created_at
updated_at
~~~

Uniqueness:

~~~
(tenant_id, catalog_type, catalog_key)
~~~

This ensures one current projection row per Tenant and catalog capability.

## 4. Entitlement statuses

Supported states:

~~~
active
scheduled_for_removal
inactive
~~~

Access semantics:

- `active` grants access while inside its time window;
- `scheduled_for_removal` still grants access until `ends_at`;
- `inactive` does not grant access.

An entitlement can also have `starts_at` and `ends_at` windows independent of the stored lifecycle status.

## 5. Entitlement sources

Supported sources:

~~~
dependency
trial
bundle
subscription
manual
~~~

Source precedence is deterministic:

~~~
dependency < trial < bundle < subscription < manual
~~~

This prevents a weaker dependency projection from overwriting a stronger direct entitlement source.

## 6. Module and Feature semantics

A direct Module entitlement makes the Module available and implicitly makes its active Features available.

A direct Feature entitlement grants that Feature without automatically granting unrelated Features.

Example:

~~~
Booking Module entitlement = active
        ↓
Booking Services Feature = available
Booking Queue Feature    = available
~~~

Feature lifecycle still matters. A `coming_soon`, `deprecated`, or `retired` Feature is not available merely because its parent Module is entitled.

## 7. Dependency semantics

Catalog dependencies are resolved transitively.

Example:

~~~
crm.deals
   ↓
crm.pipeline
   ↓
crm.contacts
~~~

Granting `crm.deals` projects its required dependencies automatically.

Access checks also re-check the dependency graph so a missing dependency cannot be bypassed by manually creating an entitlement for the dependent Feature.

Catalog Phase 3 already rejects circular dependency configuration.

## 8. Core capabilities

Core Modules do not require tenant entitlements.

They are treated as implicitly available platform capabilities.

Core catalog capabilities must not receive rows in `tenant_entitlements`.

## 9. Application service contract

`EntitlementService` exposes:

~~~
grant(Tenant, Module|Feature, attributes)
rebuildProjection(Tenant, grants)
scheduleRemoval(Tenant, Module|Feature, endsAt)
revoke(Tenant, Module|Feature)
hasModule(Tenant, key, at?)
hasFeature(Tenant, key, at?)
canUse(Tenant, capability, at?)
limit(Tenant, key, at?)
status(Tenant, key, at?)
resolveDependencies(items)
~~~

`canUse()` understands explicit `module:` and `feature:` prefixes when a caller wants unambiguous capability selection.

## 10. Projection rebuild

`rebuildProjection()` is intended for future Billing synchronization.

Conceptual input:

~~~
Tenant
  +
[
  { item: Module|Feature, source, status, quantity, starts_at, ends_at },
  ...
]
~~~

The service resolves dependencies, deduplicates the desired projection, and replaces the Tenant's current entitlement projection atomically.

Projection rebuild must be safe to repeat with the same Billing state.

## 11. Middleware

Protected routes can use:

~~~
->middleware(['tenant', 'entitled:booking.queue'])
~~~

Execution order remains:

~~~
Tenant resolution
      ↓
Entitlement check
      ↓
Membership / Permission
      ↓
Policy / Controller
~~~

The entitlement middleware does not replace authentication, Membership, permissions, or Policies.

## 12. Blade helpers

Blade helpers are available for navigation and visibility:

~~~
@entitled('booking.queue')
@featureEntitled('booking.queue')
@moduleEntitled('booking')
~~~

These helpers only control UI visibility. The backend middleware/service remains authoritative.

## 13. Permission separation

Entitlement and permission remain separate:

~~~
Tenant Entitlement
        +
User Permission
        ↓
Business authorization
~~~

A user cannot access a feature merely because the Tenant owns it.

Likewise, a permission assignment cannot grant access to a capability the Tenant does not own.

## 14. Scheduled removal

Removal follows the Billing lifecycle defined by the product rules:

~~~
Active entitlement
       ↓
Schedule removal
       ↓
ScheduledForRemoval
       ↓
ends_at reached
       ↓
Inactive
~~~

Business data is not deleted when entitlement access ends.

## 15. Tenant isolation

Entitlement records are always queried with the current Tenant identifier.

No entitlement row from Tenant A may satisfy a capability check for Tenant B.

Tenant switching must therefore recalculate entitlement access against the new Tenant context.

## 16. Caching boundary

Resolved entitlements may be cached later for high-throughput authorization.

Rules:

- Redis/cache is acceleration only;
- central database projection remains authoritative for Phase 4;
- tenant-sensitive keys include tenant identity/version;
- cache invalidation/versioning is required after entitlement changes.

## 17. Future Billing integration

Phase 5 will produce entitlement projection changes from Subscription/Subscription Item state.

Expected activation path:

~~~
Verified payment
      ↓
Subscription Item active
      ↓
Entitlement projection rebuilt
      ↓
Capability available
~~~

Expected downgrade path:

~~~
Subscription Item scheduled for end
      ↓
Entitlement scheduled_for_removal
      ↓
Period end
      ↓
Entitlement inactive
~~~

## 18. Tests

The Phase 4 suite covers:

- active feature access;
- module-to-feature inheritance;
- expiry and scheduled removal;
- tenant isolation;
- automatic dependency projection;
- source precedence;
- revoke without row deletion;
- idempotent regrant;
- projection rebuild removing stale capabilities;
- quantity limits;
- implicit core capabilities;
- entitlement middleware allow/deny behavior.

## 19. Verification gate

Local verification must include:

~~~powershell
php artisan migrate
vendor/bin/pint --dirty --format agent
php artisan test --compact
git diff --check
git status
~~~

Phase 4 becomes CLOSED / VERIFIED only after:

- local migration succeeds;
- Pint passes;
- full local test suite passes;
- no diff/check errors;
- working tree is clean and synchronized with `origin/main`;
- GitHub Actions for the implementation commit is successful.

## 20. Next phase

After this phase is verified, Phase 5 is Billing.

Billing will add subscriptions, subscription items, pricing engine behavior, invoice snapshots, refunds, and the authoritative producer that rebuilds tenant entitlements.
