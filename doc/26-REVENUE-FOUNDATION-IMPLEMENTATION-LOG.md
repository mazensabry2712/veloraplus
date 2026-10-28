# Revenue Foundation Implementation Log

**Project:** VeloraPlus  
**Work branch:** `feat/revenue-model-foundation`  
**Pull request:** [#24 — Revenue model foundation](https://github.com/mazensabry2712/veloraplus/pull/24)  
**Status:** Draft; not merged or deployed  
**Latest verified application-code commit:** `367c1472e7140049ecaaa00967473cb0b8519242`  
**Latest successful verification:** [GitHub Actions run 40651801957](https://github.com/mazensabry2712/veloraplus/actions/runs/40651801957)  
**Documentation updates:** this log and `doc/README.md` are tracked on the same feature branch; later documentation commits receive their own CI runs.

This log records the work delivered on the feature branch, the evidence gathered, and the items that remain release gates. It is not a claim that the branch is deployed or production-ready.

## 1. Product and commercial direction

- Documented the outcome-first platform vision: customers should begin with business outcomes and build a connected system from modular capabilities rather than choosing disconnected software in isolation.
- Documented the phased revenue model and readiness gates. Revenue streams that do not have a complete operational implementation are not represented as launched.
- Defined dependency billing policies as `included`, `billable`, or `free`; existing dependency edges default to `included`.
- Added server-side dependency resolution before pricing. Billable dependencies are deduplicated, retain commercial-origin / `required_by` metadata, and are represented in the quote and subscription lines.
- Accounted for dependencies already covered by a subscription and reject unsupported partial-quantity dependency upgrades or overlapping selections.

## 2. Marketplace and quote flow

- Added a tenant-facing Blade Marketplace for active modules, individually purchasable features, and bundles.
- Allowed browsing catalog items and monthly prices before subscribing; purchasing still requires an active or grace subscription.
- Added a read-only quote preview for line items, quantities, subtotal, discounts, tax, total, and separately billable dependencies.
- Kept preview side-effect free: it does not create an invoice, subscription item, or payment attempt.
- Revalidated the catalog selection and quantity on the server when the customer confirms a purchase. The interface invalidates the previous quote when the quantity changes.
- Bound quote confirmation to a server-generated HMAC token for the exact selection, subscription, priced lines, quantities, tax, dependency metadata, and total.
- Revalidate the quote token again inside the locked subscription-upgrade transaction immediately after recalculating the quote and before writing subscription items or an invoice. A stale quote therefore rolls back before any purchase records are created.
- Explain transitive dependencies in the quote: separately billed dependencies, included dependencies, free dependencies, and billable dependencies already covered by the selected app/bundle or current subscription. The explanation is included in the signed quote fingerprint.
- Prevented direct upgrades from rebilling capabilities already covered by active/pending Module grants, Bundle members, or transitive dependencies. Rejected overlapping direct Module/Feature/Bundle selections that would duplicate bundle coverage.
- A paid selection creates an upgrade invoice; a free/zero-total selection activates without an unnecessary payment attempt.
- Kept paid capabilities unavailable until payment is verified and added tests for module/feature entitlement boundaries.
- Added user-facing status feedback after Marketplace actions.

## 3. Subscription and invoice integrity

- Made initial non-trial subscription creation and initial invoice creation atomic. An invoice persistence failure rolls back the pending subscription and its items.
- Made upgrade item creation and invoice creation atomic.
- Locked and re-checked subscription state during upgrade requests so concurrent/stale requests do not create duplicate upgrade invoices.
- Made renewal processing idempotent so repeated processing of the same period end cannot create a second renewal invoice.
- Protected scheduled downgrades by locking and revalidating subscription state, refusing removal of a dependency still required by another active/pending subscription item, and updating entitlement projection transactionally.
- Added zero-value paths for initial subscriptions, upgrades, and renewals without creating payment attempts that cannot collect money.

## 4. Payment webhook safety

- Added a payload-hash consistency check for an already-known platform Kashier webhook event. Reusing the same event identity with a different payload is rejected.
- Added handling for verified, contradictory late terminal-state callbacks: these are recorded as ignored conflicts and acknowledged rather than reversing a terminal payment state or causing endless provider retries.
- Added corresponding regression coverage for platform payment notifications and late success callbacks against failed tenant payments.
- Added regression coverage for quote-token invalidation, atomic rejection of a stale quote after a price change, billing explanations for billable/included/free dependencies, module/bundle/dependency coverage, bundle overlap, and dependency-safe downgrades.
- Existing processed webhook deliveries remain idempotent.

## 5. Refund idempotency and uncertain provider outcomes

- Added request-detail checks when a refund idempotency key is reused. The same key can return the existing refund only when payment, amount, currency, and normalized reason match.
- Conflicting reuse of a key with a different amount or reason is rejected.
- Normalized empty tenant refund idempotency keys so an empty string is not accidentally treated as a reusable key.
- Kept refund records pending when the provider result is uncertain after a transport failure, reserving the amount until reconciliation instead of incorrectly marking it failed and exposing the same amount for another refund.

**Operational gap:** pending refunds with uncertain provider outcomes still need a reconciliation/operator workflow before live production processing. The pending status is a safety measure, not a complete reconciliation solution.

## 6. Other billing safeguards covered in the branch

- Serialized pending payment creation and hosted checkout creation per invoice/payment.
- Locked and re-checked invoices before voiding. Invoices with pending payments cannot be voided, and stale invoice models cannot void an invoice after payment succeeds.
- Added regression tests across dependency pricing, read-only quote behavior, entitlement boundaries, subscription/invoice atomicity, renewal idempotency, zero-value billing, webhook conflicts, checkout concurrency, invoice voiding, and refund uncertainty/idempotency.

## 7. Verification record

At application-code commit `367c1472e7140049ecaaa00967473cb0b8519242`, GitHub Actions completed successfully:

- **283 tests passed**
- **1,495 assertions**
- **30.88 seconds**
- Workflow run: [40651801957](https://github.com/mazensabry2712/veloraplus/actions/runs/40651801957)

The suite includes regressions proving that changing a catalog price after quote preview causes purchase rejection inside the locked upgrade transaction without creating an invoice/subscription item, and that billable, included, and free dependencies receive distinct customer-facing explanations. Earlier intermediate verification also passed at `89a7e992` with 282 tests and 1,488 assertions. A green suite verifies the tested commit; it does not itself certify live payment readiness or production deployment.

## 8. Remaining work before merge / production

1. Finish the guided, outcome-based recommendation and selection journey; the current Marketplace begins with catalog categories.
2. Complete release-level review and regression coverage for bundle overlap, ownership, cancellation/downgrade, quantity, and dependency lifecycle edge cases.
3. Improve item-level explanations for included/free dependencies, bundle coverage, and already-covered capabilities.
4. Implement and document the operator workflow for reconciling refunds whose provider outcome is uncertain.
5. Verify live provider credentials, callback behavior, reconciliation, operational monitoring, and production configuration before enabling live payment processing.
6. Re-run the full CI suite on the final PR head and reconcile release gates with the backend completion audit.
7. Merge only after the release gates are satisfied and explicitly approved.

## 9. Change-control boundary

- Work described here is on `feat/revenue-model-foundation` and draft PR #24.
- No merge to `main` and no production deployment was performed as part of this work.
- A green test suite is necessary evidence, but it does not by itself certify live payment readiness or production deployment.
