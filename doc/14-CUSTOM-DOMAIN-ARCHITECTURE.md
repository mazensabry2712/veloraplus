# VeloraPlus — Custom Domain Architecture

## Status

**DECISION LOCKED — architecture and business contract documented; infrastructure implementation is deferred.**

This document is the canonical reference for Custom Domain behavior.

Custom Domains are a cross-cutting capability over Tenancy, Entitlements, Billing, DNS, SSL/TLS, and public tenant routing. They are not a separate application and do not create a new Tenant.

---

## 1. Product objective

A Tenant may use either:

- the default VeloraPlus hostname: `{tenant-slug}.velora.com`;
- one or more customer-owned custom hostnames: `app.customer-domain.com` or `booking.customer-domain.com`.

The goal is to let a company present VeloraPlus-powered business experiences under its own domain while the platform keeps one shared Laravel application and the company's dedicated Tenant database.

## 2. Commercial model

Custom Domain is a **Catalog Feature** under a non-core sellable catalog Module such as `Platform Experience`.

This keeps the existing Catalog rule intact: every Feature belongs to a Module, while cross-cutting capabilities remain separate from Booking/CRM/ERP.

Conceptually:

~~~
Catalog
  ↓
Custom Domain Feature
  ↓
Entitlement
  ↓
Tenant Domain Capability
~~~

The Feature may be purchased independently or included in a Bundle/subscription configuration.

The exact price, currency, billing cycle, country override, and bundle treatment are catalog data. They are not hard-coded into routing or tenancy code.

The customer must own/control the domain being attached.

VeloraPlus does not become the customer's registrar.

## 3. Domain types

### Platform subdomain

Example:

~~~
abcclinic.velora.com
~~~

Owned and controlled by VeloraPlus.

### Custom hostname

Example:

~~~
app.abcclinic.com
booking.abcclinic.com
~~~

Owned/controlled by the customer and mapped to a Tenant.

The initial production implementation should prioritize customer-controlled hostnames/subdomains. Apex/root domains such as `abcclinic.com` may require provider-specific DNS capabilities and can be enabled when the selected edge provider supports them safely.

## 4. Customer onboarding flow

The Company Admin flow is:

~~~
Company Dashboard
      ↓
Settings
      ↓
Domains
      ↓
Add Custom Domain
      ↓
Enter hostname
      ↓
VeloraPlus creates PENDING record
      ↓
Show DNS instructions
      ↓
Customer configures DNS
      ↓
Verify ownership
      ↓
Configure/verify edge routing
      ↓
Provision/verify SSL/TLS
      ↓
Domain becomes ACTIVE
~~~

The customer is responsible for changing DNS at its registrar/provider.

VeloraPlus is responsible for the platform-side workflow and status.

## 5. DNS contract

The application must not assume one universal DNS record shape for every future edge provider.

The provider boundary must support the provider's required record(s), such as:

- CNAME for a hostname/subdomain;
- TXT for ownership verification;
- provider-specific records for apex/root domains when supported.

VeloraPlus must present an actionable instruction set containing:

- record type;
- record name/host;
- record value/target;
- verification purpose;
- expected next state.

The platform should not require the customer to know Laravel, PHP, Nginx, server IPs, or tenant database details.

The customer only configures the required DNS records.

## 6. Ownership verification

A hostname must be proven to be under the customer's control before activation.

Recommended architecture:

~~~
Domain entered
  ↓
Generate short-lived verification challenge
  ↓
Customer adds verification DNS record
  ↓
Server-side DNS check
  ↓
Verification succeeds
~~~

Verification state must be persisted centrally.

Security rules:

- challenges expire;
- challenges can be rotated;
- raw secrets are not exposed after creation;
- verification is performed server-side;
- browser redirects do not establish ownership;
- an unverified domain never becomes routable.

The exact DNS verification mechanism remains behind the Domain Verification Service.

## 7. Routing and Tenant resolution

Tenant resolution is host-driven.

~~~
Incoming request
      ↓
Trusted host extraction
      ↓
Hostname normalization
      ↓
Exact lookup in tenant_domains
      ↓
Verified + active domain?
      ├── no  → reject / fallback according to route policy
      └── yes
             ↓
        Tenant identified
             ↓
        Tenant context initialized
             ↓
        Tenant database selected
             ↓
        Authentication / Membership / Entitlement / Permission
             ↓
        Business request
~~~

Important:

- never trust a client-supplied `tenant_id` for routing;
- never select a tenant from a loose hostname suffix;
- never use substring matching;
- exact normalized host matching is required;
- a hostname maps to at most one Tenant.

## 8. Default-domain fallback

Every Tenant keeps its VeloraPlus-controlled default hostname.

Example:

~~~
abcclinic.velora.com
~~~

A broken custom domain must not make the Tenant's data or account inaccessible.

Custom domain failure affects the custom hostname, not Tenant existence.

The dashboard should expose the default hostname as the safe fallback.

## 9. Domain lifecycle

Custom-domain status is explicit:

~~~
pending
  ↓
verifying
  ↓
provisioning
  ↓
active
~~~

Failure branches:

~~~
verifying ──→ failed
provisioning ──→ failed
active ──→ disabled
~~~

Optional operational health states may be modeled separately from business availability so transient provider/DNS problems do not create ambiguous business state.

A disabled or failed domain:

- must not resolve to the Tenant for public traffic;
- must not delete Tenant records;
- must not delete business data;
- should retain diagnostic metadata for operators.

## 10. SSL/TLS

Production custom hostnames must use HTTPS.

SSL/TLS provisioning belongs to the infrastructure/edge boundary.

The application should receive a normalized provider status such as:

~~~
pending
provisioning
active
failed
~~~

The Domain model should not contain provider-specific certificate payloads.

A hostname is not ACTIVE merely because DNS verification succeeded. The platform must also confirm the required routing/edge and TLS readiness.

## 11. Central database ownership

Custom domains belong to the Central database because they are needed to identify the Tenant before selecting the Tenant database.

Canonical registry:

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

Future operational fields can include:

~~~
verification_method
verification_token_hash
verification_expires_at
last_verified_at
ssl_status
edge_status
last_checked_at
failure_code
metadata
~~~

Provider credentials do not belong in this table.

They belong in the appropriate protected infrastructure/credential store.

## 12. Provider abstraction

The core application must remain provider-neutral.

Conceptual contracts:

~~~
TenantDomainResolver
DomainVerificationService
DomainLifecycleService
CustomDomainProviderInterface
~~~

Possible provider responsibilities:

- register hostname;
- configure edge mapping;
- request/provision certificate;
- fetch current state;
- disable/remove hostname;
- return normalized health/status.

Provider-specific API payloads must remain inside `Infrastructure/`.

No Booking/CRM/ERP service may call a domain-provider SDK directly.

## 13. Entitlement integration

Custom Domain access is controlled by Tenant Entitlement.

~~~
Tenant Active
    +
Custom Domain Entitlement Active
    +
Domain Verified
    +
Domain/SSL Ready
    =
Custom Domain Available
~~~

Important distinction:

- Entitlement controls whether the Tenant is allowed to use the capability;
- Domain verification controls whether the customer controls the hostname;
- Domain operational state controls whether the hostname can actually serve traffic.

These are separate states.

## 14. Billing integration

Billing remains the commercial source of truth.

The lifecycle is:

~~~
Catalog Feature
      ↓
Selected / included in subscription
      ↓
Subscription Item
      ↓
Verified payment where required
      ↓
Subscription state active
      ↓
Entitlement active
      ↓
Custom Domain capability available
~~~

For downgrade/cancellation:

- the paid period remains honored according to billing rules;
- custom-domain access remains available until the entitlement end time;
- after entitlement removal, the custom hostname is disabled;
- the default VeloraPlus hostname remains available;
- tenant/business data is retained.

## 15. Security requirements

### Host normalization

Store a canonical hostname, not a full URL.

Reject or normalize:

- scheme;
- path;
- query string;
- fragment;
- unsupported port input;
- invalid hostname syntax.

### Reserved namespace

Customer domains must never claim VeloraPlus-owned platform namespaces.

Protected namespaces include the VeloraPlus platform domain, platform/control hostnames, and health/internal hostnames.

The exact reserved list belongs in configuration.

### Exact matching

Given `app.customer.com`, do not automatically authorize `evil-app.customer.com` or `other.customer.com` unless separately registered and verified.

### Forwarded hosts

Only configured reverse proxies/edge infrastructure may supply trusted forwarded host information.

Do not trust arbitrary `X-Forwarded-Host` headers from the public internet.

### Isolation

A custom domain is only another entry point to the same Tenant isolation model.

No custom hostname may bypass:

- Tenant context;
- Membership rules;
- Entitlements;
- Permissions;
- Policies;
- Tenant database boundaries.

## 16. Observability

Record enough information to diagnose onboarding and routing without storing secrets.

Important events include:

- domain.created;
- domain.verification_started;
- domain.verification_succeeded;
- domain.verification_failed;
- domain.provisioning_started;
- domain.provisioning_succeeded;
- domain.provisioning_failed;
- domain.disabled;
- domain.health_check_failed.

Metrics should include:

- verification success/failure rate;
- provisioning duration;
- active custom domain count;
- failed custom domain count;
- provider/API error rate;
- domain resolution latency where measurable.

## 17. Retry and recovery

External domain/SSL operations are retryable integration work.

Requirements:

- idempotent provider operations where supported;
- retry transient errors;
- back off repeated failures;
- never duplicate domain records;
- do not activate based on partial state;
- preserve diagnostic failure reason;
- make reconciliation safe to run repeatedly.

A scheduled health/reconciliation process should re-check domains that are pending or degraded.

## 18. Scaling requirements

Custom-domain routing must work across horizontally scaled application nodes.

Requirements:

- central domain lookup remains indexed;
- domain mappings may be cached;
- cache keys are tenant-aware;
- provider/SSL work is asynchronous where possible;
- no node-local domain truth;
- application nodes remain stateless;
- default VeloraPlus subdomains remain available as a fallback path.

Redis is an acceleration layer, not the permanent domain registry.

## 19. Admin UX contract

Company Admin needs:

~~~
Settings
  └── Domains
       ├── Default Domain
       ├── Custom Domains
       ├── Add Domain
       ├── Verification Status
       ├── DNS Instructions
       ├── SSL Status
       └── Disable / Remove
~~~

Each custom domain should show:

- hostname;
- status;
- verification status;
- SSL status;
- created date;
- last checked time;
- failure reason when applicable.

The UI is informative; backend state remains authoritative.

## 20. White-label relation

White-label is related but separate.

~~~
Custom Domain
    =
custom hostname

White Label
    =
branding removal/customization
~~~

A future Enterprise/Bundle offer may include both, but they must remain separate Features so the catalog can price or bundle them independently.

## 21. Initial supported production scope

The first production implementation should support:

- customer-owned subdomain/hostname;
- DNS instruction generation;
- server-side ownership verification;
- provider-backed edge routing;
- automatic/managed SSL/TLS;
- active/disabled lifecycle;
- tenant resolution by exact hostname;
- entitlement-based availability;
- safe fallback to the VeloraPlus subdomain;
- audit and health monitoring.

## 22. Explicitly deferred

Not required for the first custom-domain implementation:

- being a domain registrar;
- domain purchase/resale;
- email hosting for customer domains;
- arbitrary customer DNS management;
- multi-provider live adapters from day one;
- apex/root-domain support when the selected provider cannot support it safely;
- white-label as part of the same feature;
- custom customer email sending domains unless separately designed.

## 23. Acceptance criteria

Custom Domain is production-ready only when all of the following are true:

1. Customer can add a hostname from the Company Dashboard.
2. VeloraPlus displays correct DNS instructions.
3. Ownership cannot be marked verified from browser-side state alone.
4. Unverified domains never resolve to a Tenant.
5. One hostname cannot map to multiple Tenants.
6. Exact host matching is enforced.
7. SSL/TLS readiness is verified before ACTIVE status.
8. Provider failures are observable and retry-safe.
9. Removing entitlement disables custom-domain capability at the defined entitlement end time.
10. Default `{slug}.velora.com` remains usable after custom-domain failure/removal.
11. Custom Domain cannot bypass tenant isolation or authorization.
12. Domain lifecycle is auditable.
13. Fresh MySQL and production-scale routing tests pass.
14. No provider-specific payload leaks into Tenancy, Billing, Booking, CRM, or ERP domains.

## 24. Change control

Any future requirement involving Custom Domain, White Label, customer DNS, SSL, or edge routing must update this document and the affected tenancy/database/application/roadmap documents before implementation.
