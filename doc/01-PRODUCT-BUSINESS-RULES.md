# VeloraPlus — Product & Business Rules

## 1. Product definition

VeloraPlus is a single SaaS platform where businesses subscribe to the systems and capabilities they need.

The commercial model is:

~~~
Company
  ↓
Subscription
  ↓
Modules
  ↓
Features
  ↓
Seats / Usage where applicable
  ↓
Final Bill
~~~

The company receives one integrated dashboard and one Core data model, even when it purchases several business systems.

## 2. Company / Tenant

A Company is a commercial customer of VeloraPlus and is represented as a Tenant.

Each Tenant has:

- its own company profile;
- its own default domain/subdomain;
- optional verified custom domains when the capability is entitled;
- its own tenant database;
- its own users/memberships;
- its own staff;
- its own customers;
- its own business records;
- its own active Modules and Features;
- its own settings;
- its own billing state.

Tenant data must never leak to another Tenant.

## 3. Company profile

A company profile is a first-class business entity.

Typical information:

- display name;
- legal name;
- slug;
- logo/branding;
- phone;
- email;
- website;
- country;
- city;
- address;
- timezone;
- locale/language;
- default currency;
- tax/VAT settings;
- industry;
- business type;
- status;
- subscription status;
- active systems;
- usage;
- billing summary;
- branches/locations;
- integrations.

Platform-level company registry information is stored centrally. Company business data is stored in the tenant database.

## 4. One account, multiple companies

One platform User Account may own or belong to multiple companies.

Example:

~~~
Mazen Account
  ├── Company A → Owner
  ├── Company B → Owner
  └── Company C → Manager
~~~

The current company context must always be explicit after authentication.

A request must be evaluated using:

~~~
Authenticated Account
+
Selected Tenant
+
Membership
+
Role/Permissions
+
Tenant Entitlements
~~~

## 5. Staff

Staff is a business identity inside a Company.

The same platform person may participate in multiple companies, but each company maintains its own Staff business profile and employment/business attributes.

Do not make a separate Staff entity merely because CRM or ERP is activated.

## 6. Customer

Customer data has two levels.

### Platform-level Customer Account

Used for customer authentication and optional cross-company portal access.

The preferred initial login mechanism is phone-based verification (OTP).

### Tenant-level Customer Profile

Represents the customer's relationship with one company.

Example:

~~~
Customer Account: Ahmed / 010xxxxxxx
  ├── Company A → Customer Profile A
  ├── Company B → Customer Profile B
  └── Company C → Customer Profile C
~~~

Company A must never see Company B's private business history.

## 7. Customer 360

Within a company, the customer profile is the aggregation point for the customer's company-specific history.

Expected areas:

- personal/contact information;
- appointments;
- invoices;
- payments;
- refunds;
- notes;
- files;
- messages;
- CRM activities;
- leads/deals when CRM is active;
- timeline/business activity.

All information shown in Customer 360 must be filtered by the current Tenant.

## 8. Customer portal

The customer portal is separate from the company dashboard.

A customer may view, according to the activated capabilities:

- profile;
- upcoming appointments;
- appointment history;
- invoices;
- payment history;
- documents;
- messages;
- relevant activities.

The customer portal must never expose another customer's data.

## 9. Modules

A Module is a sellable business system.

Initial/future examples:

~~~
Booking
CRM
ERP
HR
POS
~~~

Modules are implemented inside the same Laravel application and share Platform Core services.

## 10. Features

A Feature is a sellable or included capability inside a Module.

Example:

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

A company may:

- buy the entire Module;
- buy selected Features;
- buy a Bundle containing several Modules/Features.

Features may be purchased independently unless a dependency rule says otherwise.

## 11. Bundle pricing

Bundles are curated packages of Modules/Features.

A Bundle may have a discount relative to the individual catalog prices.

The actual prices are configuration/catalog data, not hard-coded business logic.

## 12. Feature activation

A newly purchased Feature becomes active immediately after the payment is confirmed and the subscription change is committed.

Activation must be atomic from the business point of view:

~~~
Payment confirmed
    ↓
Subscription item active
    ↓
Entitlement active
    ↓
Feature available
~~~

Do not enable a paid Feature merely because the browser returned from a checkout page.

## 13. Feature disable / downgrade

Removal of a Feature is normally scheduled for the end of the current billing period.

This prevents the customer from paying for a period and unexpectedly losing access during that paid period.

When a Feature is disabled:

- access is removed according to entitlement timing;
- business data is retained;
- data should be archived/locked where appropriate;
- reactivation may restore access without recreation.

## 14. Required dependencies

A Feature may require other Features or a parent Module.

Example:

~~~
Deals
  requires Pipeline

Campaigns
  requires Contacts

ERP Sales
  requires Products

Inventory
  requires Products
~~~

Default policy: required dependencies are auto-activated.

When a dependency is billable, its price must be visible in the pricing summary or covered explicitly by a Bundle rule.

## 15. Trial

Default company trial: 14 days.

During trial:

- the company may choose Modules and Features;
- selected capabilities are available according to trial policy;
- the system tracks the exact selected catalog components;
- conversion to paid subscription should reuse the same selected configuration unless the company changes it.

## 16. Billing cycles

Supported cycles:

- Monthly
- Yearly

Yearly pricing may be cheaper than twelve monthly payments through explicit catalog configuration.

## 17. Payments

There are two distinct financial directions.

### Company → VeloraPlus

The company pays VeloraPlus for its SaaS subscription.

### Customer → Company

A company's customer pays the company for business transactions such as bookings, invoices, or sales.

These flows must not share the same accounting assumptions.

## 18. Payment provider

Initial provider for VeloraPlus subscription billing: Kashier.

The application must use a provider abstraction so the Billing domain is not hard-coded to Kashier.

Future provider examples can implement the same contract.

## 19. Company merchant account

For company customer payments, the preferred architecture is that each company can connect its own merchant/payment-provider account.

Provider credentials must be encrypted and must never be rendered into Blade/JavaScript.

## 20. Money

No floating-point arithmetic for money.

Recommended representation:

~~~
amount_minor = integer
currency      = ISO-like 3-letter code
~~~

Example:

~~~
1,500.00 EGP
↓
150000 minor units
~~~

Use a money value object/library in application code.

## 21. Currency / locale / timezone

Each company has its own:

- country;
- currency;
- timezone;
- locale;
- number/date formatting;
- tax settings.

The VeloraPlus platform catalog may have multiple prices by country/currency.

## 22. Tax

Tax must be explicit in the pricing/invoice model.

The billing model should be able to represent:

- subtotal;
- discount;
- taxable amount;
- tax amount;
- total;
- currency.

Tax rules must be tenant/country-aware and must not be hard-coded into controllers.

## 23. Branches

Companies may have multiple branches/locations.

Booking, staff assignments, services, CRM activity, and future ERP records may optionally be scoped to a branch where required.

## 24. Branding

A company may configure:

- logo;
- primary/secondary branding values;
- public booking branding;
- invoice branding;
- email branding.

## 25. URL and Custom Domain strategy

### Default tenant domain

Every Tenant receives a platform-controlled default hostname:

~~~
{tenant-slug}.velora.com
~~~

The default hostname remains available even when a custom domain is not active.

### Custom domains

A Tenant may attach one or more customer-owned hostnames, for example:

~~~
app.customer-domain.com
booking.customer-domain.com
~~~

A custom domain is a tenant-owned routing alias, not a second Tenant and not a separate application.

Commercial classification:

~~~
Catalog Feature: Custom Domain
        ↓
Entitlement
        ↓
Tenant Domain Capability
~~~

The feature may be sold independently or included through a Bundle/plan. The actual amount, currency, billing cycle, country override, and bundle treatment are catalog data and must never be hard-coded.

### Customer responsibility

The customer buys and owns the domain through its registrar. VeloraPlus does not become the registrar.

VeloraPlus is responsible for:

- generating the DNS instructions;
- generating/rotating verification material;
- validating ownership;
- registering the hostname against the Tenant;
- coordinating SSL/TLS provisioning through the selected edge/domain provider;
- exposing the domain lifecycle/status to the Company Admin;
- resolving verified active hosts to exactly one Tenant.

### Domain onboarding

The expected flow is:

~~~
Company Dashboard
    ↓
Settings → Domains
    ↓
Enter hostname
    ↓
VeloraPlus creates pending domain record
    ↓
DNS verification instructions
    ↓
Customer configures DNS at registrar
    ↓
Ownership verification
    ↓
Traffic/routing verification
    ↓
SSL/TLS provisioning
    ↓
Domain ACTIVE
~~~

Redirect/return from a provider UI is never proof of ownership or payment. Domain activation is based on server-side verification.

### Domain lifecycle

A custom domain should move through explicit states:

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

A failed or disabled custom domain must not delete the Tenant or its business data.

### Resolution/security rules

Routing must:

- normalize and canonicalize hostnames;
- use the actual trusted request host;
- ignore arbitrary tenant IDs submitted by the client;
- accept only verified/active domain records;
- prevent one hostname from being attached to multiple Tenants;
- perform exact hostname matching;
- reject reserved VeloraPlus platform hostnames;
- use HTTPS in production;
- trust forwarded host information only from configured reverse proxies/edge infrastructure.

### Default-domain fallback

If a Tenant custom domain becomes unavailable, the Tenant VeloraPlus subdomain remains the canonical fallback entry point.

The platform must not make a Tenant unreachable solely because a custom domain DNS, SSL, or edge configuration failed.

### Boundary

Custom Domain capability does not change Tenant isolation, Tenant database selection, Membership, RBAC, or business ownership.

The request path remains:

~~~
Host
  ↓
Tenant Domain Resolver
  ↓
Tenant Context
  ↓
Tenant Database
  ↓
Membership / Entitlement / Permission as applicable
  ↓
Business request
~~~

The detailed technical contract is defined in doc/14-CUSTOM-DOMAIN-ARCHITECTURE.md.

## 25A. Public Web & SEO

Public Web + SEO is a cross-cutting platform capability for public company presence. It is not automatically a separate commercial Module or Feature.

The public surface may expose Tenant branding, company information, published Services, and Booking entry points according to the Tenant's enabled public capabilities.

SEO rules:

- public pages are server-rendered and crawlable;
- each indexable page has a meaningful title, description, and canonical URL;
- private workspace/account/payment/customer pages are non-indexable;
- public Service URLs use stable Tenant-local slugs;
- Tenant public metadata is isolated from other Tenants;
- canonical host selection follows the active primary public domain;
- sitemap and robots policies are host-aware;
- structured data describes only visible public facts;
- SEO metadata never becomes an authorization mechanism.

The technical contract is defined in doc/19-SEO-AND-PUBLIC-WEB-ARCHITECTURE.md.
## 26. Deletion and retention

Business data is not hard-deleted merely because a Feature was disabled, a Module was removed, or a Subscription expired.

Company cancellation should have a retention/archive lifecycle.

Default product policy:

~~~
Cancel
  ↓
Stop renewal
  ↓
Current paid period may remain active
  ↓
Archive/suspend after expiry
  ↓
Retention period
  ↓
Permanent deletion only by explicit lifecycle policy
~~~

## 27. Admin access

### Platform Admin

Operates VeloraPlus itself:

- Tenants;
- Modules;
- Features;
- Pricing;
- Bundles;
- Subscriptions;
- Invoices;
- Payments;
- usage;
- support;
- audit;
- platform settings.

### Company Admin

Operates one Tenant:

- company profile;
- users;
- staff;
- customers;
- branches;
- activated Modules/Features;
- business operations;
- company billing;
- integrations;
- settings.

## 28. Dashboard behavior

The Company Dashboard is dynamic.

Visible navigation is determined by:

~~~
Active Tenant
+
Tenant Entitlements
+
User Permissions
~~~

The UI may hide inaccessible areas, but the backend must always enforce access.

## 29. Industry presets

Industry can influence recommended Modules/Features.

Recommendations must not create separate code branches/forks of the application.

## 30. Future systems

The architectural roadmap includes Booking, CRM, ERP, HR, POS, and additional vertical modules.

Only Booking is part of the first MVP delivery. Future modules reuse the same Core entities and services.
