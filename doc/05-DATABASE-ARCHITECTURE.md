# VeloraPlus — Database Architecture

## 1. Database strategy

The system uses one central/control database and one dedicated database per Tenant.

## 2. Central database

### Platform accounts

~~~
platform_accounts
-----------------
id (ULID stable identifier)
email
email_verified_at
password_hash / auth data
status
created_at
updated_at
~~~

### Tenants

~~~
tenants
-------
id (ULID stable identifier)
name
legal_name
slug
status
industry
country_code
default_currency
timezone
locale
created_at
updated_at
~~~

### Tenant domains

`tenant_domains` is a central registry because a hostname identifies a Tenant before the application can select the Tenant database.

Current Phase 1 foundation:

~~~
tenant_domains
--------------
id (ULID)
tenant_id
domain
type
is_primary
status
verified_at
created_at
updated_at
~~~

Future Custom Domain delivery may extend the central record with operational verification/edge state without moving the registry into a tenant database. The extension should be additive and should preserve the central uniqueness rule.

Conceptual future fields include:

~~~
verification_method
verification_token_hash
verification_expires_at
last_verified_at
ssl_status
edge_status
last_checked_at
failure_code / metadata
~~~

Sensitive verification material must be stored as hashes or protected secrets where possible; raw credentials or provider secrets do not belong in `tenant_domains`.

The platform must keep:

- unique normalized hostname globally;
- one hostname mapped to one Tenant;
- explicit domain type;
- explicit lifecycle status;
- server-side verification timestamps;
- provider references/operational state separate from business Tenant data.

### Memberships

~~~
tenant_memberships
------------------
id
tenant_id
account_id
role_key / role_id
status
joined_at
created_at
updated_at
~~~

### Catalog

The implemented central catalog schema is:

~~~
modules
--------
id
key
name
description
status
is_core
sort_order
metadata

features
--------
id
module_id
key
name
description
status
billing_mode
is_required
is_individually_purchasable
sort_order
metadata

catalog_dependencies
---------------------
id
dependent_type
dependent_id
dependency_type
dependency_id
metadata

bundles
-------
id
key
name
description
status
discount_bps
sort_order
metadata

bundle_modules
--------------
bundle_id
module_id

bundle_features
---------------
bundle_id
feature_id
~~~

Module/Feature/Bundle catalog data is central and uses ULIDs. Polymorphic dependency and price targets are protected by application-level validation because a relational FK cannot target multiple catalog tables.

### Prices

Central price catalog:

~~~
catalog_prices
--------------
id
priceable_type
priceable_id
billing_cycle
currency
country_code
amount_minor
status
effective_from
effective_to
metadata
~~~

Prices support Module, Feature, and Bundle targets, monthly/yearly cycles, global prices with optional country overrides, integer minor-unit amounts, lifecycle status, and effective windows.

### Tenant entitlements

The Phase 4 entitlement projection is central because capability access is determined after Tenant resolution but before business-module authorization.

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

The canonical uniqueness rule is:

~~~
(tenant_id, catalog_type, catalog_key)
~~~

Supported lifecycle states are active, scheduled_for_removal, and inactive. Supported projection sources are dependency, trial, bundle, subscription, and manual.

This table is a rebuildable authorization projection, not the financial source of truth. Future Subscription/Subscription Item state will remain authoritative for Billing.

### Subscriptions

~~~
subscriptions
-------------
id
tenant_id
status
billing_cycle
currency
country_code
starts_at
trial_ends_at
current_period_start
current_period_end
next_billed_at
cancel_at_period_end
cancelled_at
provider
provider_reference
subtotal_minor
discount_minor
tax_minor
total_minor
metadata
created_at
updated_at
~~~

### Subscription items

~~~
subscription_items
------------------
id
subscription_id
item_type
catalog_type
catalog_key
catalog_price_id
quantity
unit_amount_minor
discount_amount_minor
tax_amount_minor
line_total_minor
currency
status
starts_at
ends_at
activation_source
provider_reference
metadata
created_at
updated_at
~~~

### Platform invoices

~~~
platform_invoices
-----------------
id
tenant_id
subscription_id
number
status
currency
subtotal_minor
discount_minor
tax_minor
total_minor
issued_at
due_at
paid_at
metadata
created_at
updated_at
~~~

### Platform invoice items

~~~
platform_invoice_items
----------------------
id
invoice_id
subscription_item_id
description
catalog_type
catalog_key
quantity
unit_amount_minor
discount_minor
tax_minor
line_total_minor
metadata
created_at
updated_at
~~~

### Platform payments

~~~
platform_payments
-----------------
id
tenant_id
invoice_id
provider
provider_payment_id
provider_event_id
amount_minor
currency
status
paid_at
metadata
created_at
updated_at
~~~

### Webhook events

~~~
webhook_events
--------------
id
provider
provider_event_id
event_type
status
received_at
processed_at
payload_hash
payload / metadata
~~~

Unique rule:

~~~
(provider, provider_event_id)
~~~

### Payment provider accounts

~~~
payment_provider_accounts
-------------------------
id
tenant_id
provider
account_reference
status
encrypted_credentials
metadata
created_at
updated_at
~~~
Provider account records are configuration/credential records and do not make a concrete provider part of Core Billing.

Payment ownership is separated by financial domain:

~~~
Central platform:
  Company → VeloraPlus
  Subscription
  Platform Invoice
  Platform Payment

Tenant business:
  Customer → Company
  Booking / Sales / Tenant Invoice / Tenant Payment
~~~

Tenant business payment records belong to the current Tenant business domain. Their exact tables may be module-specific, but they must not be confused with central Platform Billing records.

## 3. Tenant database

### Company settings

~~~
company_settings
----------------
id
key
value
type
created_at
updated_at
~~~

Important business settings should use typed entities/columns when required.

### Staff

~~~
staff
-----
id (ULID stable tenant identifier)
account_id (reference identifier, not FK)
location_id (tenant FK)
name
phone
email
status
metadata
created_at
updated_at
deleted_at
~~~

`account_id` references a central Platform Account identifier and is never a cross-database foreign key.

### Customers

~~~
customers
---------
id (ULID stable tenant identifier)
customer_account_id (nullable reference identifier)
name
phone
email
status
source
notes
metadata
created_at
updated_at
deleted_at
~~~

`customer_account_id` references an optional central Customer Account identity and is never a cross-database foreign key.

### Locations

~~~
locations
---------
id (ULID stable identifier)
name
code
address
country_code
city
timezone
status
metadata
created_at
updated_at
~~~

### Services

~~~
services
--------
id (ULID stable identifier)
name
description
duration_minutes
buffer_before_minutes
buffer_after_minutes
price_minor
currency
deposit_amount_minor
status
online_bookable
capacity
metadata
created_at
updated_at
deleted_at
~~~

### Tenant payments

Tenant payment records are tenant-local.

Fields:

- id
- appointment_id
- customer_id
- provider
- merchant_order_id
- provider_payment_id
- provider_order_id
- provider_session_id
- amount_minor
- currency
- status
- paid_at
- failed_at
- refunded_at
- metadata
- created_at
- updated_at

Merchant account credentials remain central and encrypted in payment_provider_accounts.

## 4. Booking tables

Initial conceptual set:

~~~
staff_working_hours
staff_breaks
staff_time_off
staff_services
appointments
appointment_items
resources
resource_service
queues
queue_entries
appointment_status_histories
booking_settings
~~~

## 5. CRM future tables

~~~
crm_contacts
crm_leads
crm_pipelines
crm_pipeline_stages
crm_deals
crm_activities
crm_tasks
crm_campaigns
crm_customer_timeline_entries
~~~

CRM must reuse tenant Customer/Staff identities.

## 6. ERP future tables

~~~
products
product_categories
warehouses
warehouse_stocks
stock_movements
suppliers
purchase_orders
purchase_order_items
sales_orders
sales_order_items
expenses
financial_accounts
~~~

## 7. Identifier strategy

Use stable opaque public identifiers.

Recommended options: UUID or ULID.

Internal integer IDs may still be used where appropriate, but external IDs should be non-sequential when security or correlation requires it.

## 8. Tenant references

Because central and tenant databases are separate:

- no cross-database FK constraints;
- stable identifiers for central-to-tenant references;
- normal tenant-local foreign keys are allowed inside a tenant database;
- application validation for central records;
- tenant-aware caching.

## 9. Indexing rules

Index based on actual query patterns.

Examples:

Customers:
~~~
phone
email
id
~~~

Appointments:
~~~
starts_at
staff_id + starts_at
location_id + starts_at
customer_id + starts_at
status
~~~

Subscriptions:
~~~
tenant_id
status
current_period_end
provider_reference
~~~

Invoices:
~~~
tenant_id + issued_at
status
number
~~~

## 10. Uniqueness rules

Catalog-specific rules include:

- module key unique;
- feature key unique;
- bundle key unique;
- bundle/module membership unique;
- bundle/feature membership unique;
- catalog dependency edge unique;
- active pricing dimensions must not overlap at the application layer.

Examples:

- tenant slug unique centrally;
- normalized domain unique centrally;
- account/tenant membership unique;
- a verified/active custom hostname resolves to exactly one Tenant;
- custom-domain operational state remains central and is not stored in tenant databases;
- module key unique;
- feature key unique;
- provider event unique per provider;
- business codes unique within tenant when appropriate.

## 11. Soft deletion

Use soft deletion for restorable business entities where appropriate.

Do not use soft deletes as a substitute for entitlement retention.

## 12. Migration strategy

Central migrations live in the central migration path.

Tenant migrations must have an isolated execution path compatible with the selected tenancy implementation.

Migrations must be:

- deterministic;
- forward-focused;
- deployable;
- tested.

## 13. Seed strategy

Seed platform:

- roles/permissions;
- Modules;
- Features;
- dependencies;
- Bundles;
- development pricing;
- test fixtures.

Production pricing changes are audited and must not rely on destructive reseeding.

## 14. Historical data rule

Invoices and payments are immutable historical records from a business perspective.

Never mutate old financial amounts because catalog prices changed.

## 15. Data ownership rule

Central database owns platform identity and platform billing state.

Tenant database owns company business activity.

Example:

~~~
Central:
  Subscription #123
  Platform Invoice #INV-1001

Tenant:
  Appointment #A-500
  Customer #C-30
  Tenant Invoice #TINV-900
~~~

## 16. Provisioning

Tenant database provisioning must be idempotent.

A tenant is not Ready until database creation and baseline installation have completed successfully.

## 17. Backup boundaries

Back up:

- central database;
- every tenant database;
- object/file storage;
- secure operational configuration as appropriate.

A tenant restore should not require restoring unrelated tenant business data.

## 18. Database design principle

Schema separation is a security boundary, not a permission substitute.

Every application service still operates only in the current Tenant context.


## Current Core tenant entities

`company_settings`, `locations`, `staff`, and `customers` use ULID primary identifiers in the tenant database. Tenant-local relationships may use database foreign keys; central identifiers such as `staff.account_id` and `customers.customer_account_id` remain application-level references only.

## Current identifier implementation

For the current Foundation/Phase 1 implementation, ULIDs are used directly as primary identifiers for Platform Accounts, Tenants, Tenant Domains, and Tenant Memberships. A separate sequential internal ID/public ID pair is not used. This keeps identifiers opaque and stable across central/tenant boundaries. Additional identity fields such as phone may be introduced during the Identity phase without changing this identifier strategy.


### Platform refunds

~~~
platform_refunds
----------------
id
tenant_id
payment_id
amount_minor
currency
status
reason
provider_refund_id
initiated_by_account_id
processed_at
metadata
created_at
updated_at
~~~

Refunds are separate financial records and do not mutate historical invoice totals.

### Platform credits

~~~
platform_credits
----------------
id
tenant_id
amount_minor
remaining_minor
currency
status
source
expires_at
metadata
created_at
updated_at
~~~

Credits are separate from refunds and may be consumed by future invoices.

### Billing audit events

~~~
billing_audit_events
--------------------
id
tenant_id
actor_account_id
action
subject_type
subject_id
metadata
created_at
updated_at
~~~

Billing audit records remain in the central database and are tenant-scoped.

## Public Web / SEO data

Public SEO defaults are Tenant-owned configuration and use the existing company_settings key/value table with namespaced keys such as:

~~~
seo.site_title
seo.site_description
seo.default_og_image
seo.robots
seo.locale
~~~

Public Service slugs and future page-level SEO overrides belong in the Tenant database because they describe Tenant-owned public content. They must never become shared central business content.

SEO metadata does not create a new cross-database relationship and must follow the existing Tenant isolation boundary.
