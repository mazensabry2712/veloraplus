# VeloraPlus — Catalog Commerce Dependency Audit

## Status

**AUDIT FINDINGS — IMPLEMENTATION REQUIRED BEFORE DEPENDENCY-AWARE SELF-SERVE COMMERCE**

This audit describes the GitHub main snapshot inspected during the revenue-model review. It does not certify a local test run or a production deployment.

## 1. Existing implementation

- Catalog records support Modules, Features, Bundles, prices, and dependency edges.
- A dependency edge can connect a Module or Feature to another Module or Feature.
- The dependency graph manager rejects self-dependencies, duplicate edges, and circular graphs.
- The dependency resolver performs transitive graph traversal and deduplicates nodes by morph type and ID.
- Entitlement grant/projection resolves dependencies and evaluates dependencies during access checks.
- Subscription and subscription items are the financial source of truth; tenant entitlements are a rebuildable authorization projection.
- Marketplace purchase still submits a root catalog selection through the existing billing upgrade flow.
- Subscription creation and upgrade now call a server-side commercial resolver before PricingEngine, so dependencies explicitly marked billable can be added to the quote and persisted as subscription items. The resolver records commercial origin and the root selections that require each billable dependency.
- Billing documents defer usage metering; per-seat and usage modes are catalog-ready labels, not a complete metering product.

## 2. Critical commercial gaps

### 2.1 Quote and purchase do not share a dependency-expanded item set

A customer can select a Feature whose dependencies are resolved for authorization, while the quote and subscription item list still contain only the requested item. This can produce a commercial mismatch between what is granted and what is billed.

The resolver now expands billable dependency lines before quote creation and the same quote lines are used for invoice items and subscription items. Remaining work is to prove bundle overlap, ownership/quantity edge cases, cancellation/downgrade, and the feature-granular entitlement contract with tests before treating the whole commerce flow as release-ready.

### 2.2 Dependency billing policy is not yet enforced end-to-end

The dependency edge now validates and stores a billing_policy value, and the subscription resolver applies it when expanding billable dependencies. The UI does not yet expose this policy as a customer-facing quote explanation, and bundle overlap/ownership edge cases still require regression coverage.

Stored policy contract (the billing integration is still required):
- included: dependency is automatically selected/granted but does not add a separate price line because the parent price includes it.
- billable: dependency is separately priced unless already covered by an active subscription item or bundle.
- free: dependency is required and granted but has no charge; it must not carry a customer charge.
- Missing policy must use a documented, deterministic catalog default and must be displayed in the quote. It must never create an invisible charge.

The chosen policy must be stored centrally and validated by catalog management, not inferred from a browser payload.

### 2.3 Shared dependencies must not be billed twice

If two selected features require the same dependency, the quote should contain one dependency line/selection with a clear reason list. If the tenant already owns an effective dependency, it should not be billed again for the same covered quantity. Quantity differences require explicit semantics before being supported.

### 2.4 Module entitlement semantics conflict with granular selection

The existing EntitlementService treats an effective Module entitlement as availability for all active Features under that Module. Therefore selling a Module currently means broader access than selecting only some of its Features.

Before enabling app-level selection plus feature-level purchases, the product contract must decide whether:
- buying a Module is an explicit license to all included active Features; or
- a Module is primarily a container and app visibility is derived from at least one granted Feature plus any app-shell/base capability.

Do not change this entitlement contract in isolation. Update the entitlement service, projector, middleware, dashboard visibility, bundle behavior, and tests together. Preserve the current behavior until that cross-cutting change is tested.

### 2.5 Bundles and dependencies can overlap

Bundle pricing is currently an explicit catalog price; bundle members determine grants, not the price calculation. Dependency expansion must not replace the bundle's approved price with a sum of component prices.

When a selected bundle overlaps a directly selected item or a required dependency, the quote must clearly distinguish:
- bundle-covered capabilities;
- separately selected items;
- required items not covered by the bundle;
- capabilities already owned.

No overlap should create duplicate subscription items or unexpected charges.

## 3. Canonical commercial resolution contract

The current server-side resolver accepts trusted catalog model instances and quantities. It should return a normalized, deterministic selection containing at least:

- requested root items;
- transitive required dependencies;
- per-item origin/reason;
- dependency billing policy;
- selected quantity and validated quantity rules;
- existing effective subscription coverage;
- bundle coverage;
- items that require a price line;
- items that are granted but included/free;
- warnings or errors for unavailable dependencies;
- a stable fingerprint or version for quote/checkout consistency.

The browser may preview the selection, but the server must recompute and validate it during quote/purchase. Never trust client-supplied prices, dependency lists, included flags, or entitlement claims.

## 4. Required integration points

1. Catalog dependency management: validate commercial metadata and expose policy through the application service.
2. Marketplace overview/selection: show included and billable dependencies, ownership, and readiness.
3. Pricing: quote the canonical normalized list exactly once, including shared-dependency deduplication.
4. Subscription upgrade/create: persist the same normalized billable items, with explicit origin metadata.
5. Invoice lines: preserve the approved price, description, quantity, and origin/reason snapshot.
6. Entitlement projection: grant all required capabilities without depending on client state.
7. Cancellation/downgrade: do not remove a dependency while an active purchased root still requires it.
8. Audit: record requested roots, expanded items, policy, quote, and resulting subscription changes.
9. UI: show direct selections, auto-added requirements, included items, existing coverage, and total before confirmation.

## 5. Test matrix

Minimum regression coverage before launch:

- direct Feature with no dependencies;
- direct Feature with one billable dependency;
- direct Feature with an included/free dependency;
- multiple roots sharing one dependency;
- transitive dependency chain;
- circular graph rejected by catalog management;
- missing, retired, or inactive dependency blocks purchase;
- dependency already owned is not charged again;
- module dependency and module-versus-feature entitlement semantics;
- bundle with modules/features and dependency overlap;
- quantity validation and explicit quantity compatibility;
- monthly/yearly prices and country-specific price resolution;
- tax, discount, integer money, and overflow handling;
- quote and persisted invoice/subscription lines agree exactly;
- payment failure never activates paid root or dependency;
- verified payment activates the exact intended capability set;
- upgrade retry/duplicate submission is idempotent or safely rejected;
- concurrent upgrades cannot create duplicate billable items;
- dependency removal cannot break another active root;
- tenant isolation and billing permissions;
- downgrade, cancellation, refund, and trial-expiry regression.

## 6. Delivery order

1. Complete the existing Phase 8.6 verification/release gate.
2. Finish tests for the new dependency-aware resolver and resolve any unrelated CI failures separately.
3. Agree and implement the Module-versus-Feature entitlement semantics consistently across UI, middleware, projector, and tests.
4. Add bundle/ownership/cancellation/quantity edge-case coverage and customer-facing quote explanations.
5. Complete the marketplace UI integration and server-side revalidation.
6. Run the full test suite and CI; reconcile docs and release gates.
7. Only then enable real self-serve commercial selection and advertise it.

## 7. Explicit non-goals for this increment

- Do not seed fictional CRM or ERP products as production-ready.
- Do not turn on per-seat or usage billing before metering exists.
- Do not enable payment commissions, partner payouts, or AI usage charges.
- Do not change production subscriptions or customer data.
- Do not activate new paid items without verified payment.

## 5. Read-only upgrade quote preview

The backend now exposes an authorized Marketplace preview endpoint:

- `POST /dashboard/marketplace/preview`
- Uses the same catalog item validation, dependency expansion, subscription cycle, currency, and pricing engine as the upgrade request.
- Returns quote totals and line metadata, including `commercial_origin` and `required_by` for required billable dependencies.
- Preserves the subscription's configured `tax_bps`, matching the actual Marketplace upgrade path.
- Does not create subscription items, invoices, or payment attempts.
- Uses `SubscriptionService::previewUpgrade()` as the shared quote boundary; the actual upgrade request calls the same method before creating pending subscription items.
- Requires the current tenant context and billing-management authorization.
- This is a backend contract only. The customer-facing quote UI remains unimplemented, and the endpoint does not mean the full purchase journey is ready to launch.

