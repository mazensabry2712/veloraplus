# VeloraPlus — Modules, Features & Entitlements

## 1. Purpose

The Module system turns VeloraPlus into a single platform with selectable systems.

Three concepts must remain separate:

- Module: a business system.
- Feature: a capability inside a Module.
- Entitlement: proof that a Tenant is currently allowed to use a capability.

## 2. Module examples

~~~
Booking
├── Services
├── Appointments
├── Availability
├── Queue
├── Resources
└── Reports
~~~

~~~
CRM
├── Contacts
├── Leads
├── Deals
├── Pipeline
├── Activities
├── Tasks
├── Campaigns
└── Customer Timeline
~~~

~~~
ERP
├── Products
├── Inventory
├── Warehouses
├── Suppliers
├── Purchasing
├── Sales
├── Expenses
└── Finance
~~~

## 3. Catalog model

The catalog is centrally managed.

A Module should have:

- stable key;
- name;
- description;
- status;
- is_core;
- sort order;
- metadata.

A Feature should have:

- stable key;
- module;
- name;
- description;
- status;
- billing mode;
- required/optional behavior;
- metadata.

Cross-cutting platform capabilities follow the same hierarchy. For example, Custom Domain belongs under a non-core catalog Module such as `Platform Experience`; it is not a standalone Feature outside the Module catalog.

Typical example:

~~~
Platform Experience
├── Custom Domain
└── White Label (future)
~~~

Use stable machine keys such as:

~~~
booking
crm
crm.contacts
crm.leads
crm.pipeline
erp
erp.products
erp.inventory
~~~

## 4. Core vs sellable Modules

Platform Core is not a normal customer-purchasable Module.

Core examples:

- tenancy;
- authentication;
- membership;
- permissions;
- company profile;
- settings;
- billing infrastructure;
- notifications infrastructure;
- audit infrastructure.

Business Modules are customer-facing commercial systems.

## 5. Individual Feature purchase

A Feature may be bought without the entire Module as long as dependencies are satisfied.

## 6. Full Module purchase

A Module bundle can activate all included Features according to catalog rules.

The system should distinguish:

- purchased directly;
- included by Bundle;
- auto-activated as dependency.

## 7. Entitlement authority

The backend is authoritative.

A Feature can be available only when:

1. the Tenant has a valid subscription state;
2. the Tenant has the required entitlement;
3. dependencies are satisfied;
4. the current user has required permissions.

Conceptually:

~~~
Can Access
=
Tenant Active
AND
Entitlement Active
AND
User Permission
AND
Dependency Rules Satisfied
~~~

## 8. Entitlement service

Implement a central service such as EntitlementService.

Expected operations:

~~~
hasModule(Tenant, key)
hasFeature(Tenant, key)
canUse(Tenant, capability)
limit(Tenant, metric)
status(Tenant, key)
resolveDependencies(items)
~~~

Do not scatter entitlement logic through controllers.

## 9. UI behavior

Blade may use entitlement helpers to hide menus, but backend access must always be enforced independently.

## 10. Permission vs entitlement

They are different dimensions.

Example:

~~~
Tenant entitlement:
CRM Leads = active

User permission:
crm.leads.view = false
~~~

The user still cannot view leads.

Conversely, a user permission does not grant access when the Tenant has no active entitlement.

## 11. Dependencies

Dependency graph is configuration data.

Example:

~~~
crm.deals
  requires crm.pipeline

crm.campaigns
  requires crm.contacts

erp.sales
  requires erp.products
~~~

Required dependency policy:

- auto-activate by default;
- show the dependency in pricing summary;
- prevent circular dependencies;
- validate the graph transactionally.

## 12. Entitlement projection

Subscription Items are the billing source of truth.

Tenant entitlements may be stored as a projection/cache for fast authorization.

If both exist:

~~~
Subscription + Subscription Items
            ↓
      Entitlement Resolver
            ↓
    Tenant Entitlement Projection
~~~

The projection must be rebuildable.

### Phase 4 implementation semantics

- Tenant entitlements are stored centrally as a tenant-specific projection.
- A Module entitlement makes its active Features available; a Feature entitlement does not grant unrelated Features.
- Required catalog dependencies are projected automatically and are re-checked during access evaluation.
- Core Modules are implicitly available and do not receive tenant entitlement rows.
- `scheduled_for_removal` continues to grant access until `ends_at`; `inactive` does not.
- Projection sources use deterministic precedence: dependency < trial < bundle < subscription < manual.
- `EntitlementService::rebuildProjection()` is the future Billing integration boundary and is not a financial source of truth.
## 13. Immediate activation

Feature purchase flow:

~~~
Select Feature
  ↓
Resolve dependencies
  ↓
Calculate price
  ↓
Create/change pending subscription item
  ↓
Checkout
  ↓
Provider confirms payment
  ↓
Webhook verified
  ↓
Subscription item activated
  ↓
Entitlements projected
  ↓
Feature available immediately
~~~

## 14. Scheduled disable

~~~
User requests removal
  ↓
Current item marked scheduled_for_end
  ↓
Feature stays active until period end
  ↓
Period closes
  ↓
Entitlement becomes inactive
  ↓
Business data remains
~~~

## 15. Reactivation

Previously disabled Features retain data.

Re-purchasing the Feature restores access to retained data.

## 16. Module marketplace UX

Company-facing catalog should provide:

~~~
My Systems
Available Systems
Bundles
Add-ons
Billing
~~~

For each Module show:

- description;
- included Features;
- individual Feature prices;
- Bundle price/discount;
- dependencies;
- currently active components.

## 17. Commercial catalog examples

Initial launch may expose Booking. CRM/ERP catalog definitions may exist as future placeholders, but they must not be purchasable before their implementation is ready.

## 18. Module states

Suggested:

~~~
draft
active
coming_soon
deprecated
retired
~~~

## 19. Feature states

Suggested:

~~~
draft
active
coming_soon
deprecated
retired
~~~

## 20. Non-negotiable rules

- Entitlements are tenant-specific.
- Permissions are user-specific.
- Modules are not separate apps.
- Feature data is not duplicated without a business reason.
- Entitlement evaluation is deterministic.
- Catalog changes are audited.
- Historical invoices preserve billed price/name snapshots.
