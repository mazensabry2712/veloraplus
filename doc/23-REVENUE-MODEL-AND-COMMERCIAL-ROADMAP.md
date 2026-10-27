# VeloraPlus — Revenue Model & Commercial Roadmap

## Status

**PROPOSED COMMERCIAL CONTRACT — NOT A CLAIM THAT ALL REVENUE CHANNELS ARE IMPLEMENTED**

This document defines the order in which VeloraPlus should validate and build revenue channels. Existing Billing/Catalog code remains the source of truth for any currently implemented subscription behavior. No new payment, fee, or revenue channel is enabled by this document alone.

## 1. Product thesis

VeloraPlus should be an outcome-first modular business platform: companies select business outcomes and capabilities, the platform recommends a connected set of applications, resolves mandatory dependencies transparently, quotes the full commercial impact, and grants only the capabilities paid for or otherwise explicitly included.

The product should monetize durable business value rather than an excessive number of tiny toggles.

## 2. Revenue portfolio

### Tier A — Launch revenue

1. **Recurring subscriptions**
   - Monthly and annual plans built from purchasable catalog items.
   - A clear included allowance and transparent upgrade path.
   - Annual pricing may be discounted only through explicit catalog pricing, not hidden calculations.

2. **Paid implementation and onboarding**
   - Data import, initial setup, configuration, training, and supported migration.
   - Fixed-scope packages where possible; custom work quoted separately.
   - This is a service revenue stream, not a subscription entitlement.

3. **Commercial add-ons**
   - Additional implemented capabilities, limits, branches, seats, or storage only when their metering and enforcement contracts are complete.
   - Do not sell per-seat or usage billing modes as operational until usage collection, aggregation, limits, invoice calculation, and audit are implemented and tested.

### Tier B — Expansion revenue

4. **Partner implementation and referral network**
   - Certified implementers can provide onboarding and custom work.
   - VeloraPlus may charge disclosed referral or certification fees only under explicit agreements.
   - Do not present partner revenue as guaranteed.

5. **Integrations and API plans**
   - Price by documented integration capability, API allowance, or support level.
   - Enforce limits server-side and provide usage visibility before charging overages.

6. **AI-assisted operations**
   - Optional analytics, forecasting, assisted reporting, and workflow automation.
   - Price through a clear tier or usage allowance with cost controls.
   - AI output must be explainable enough for business users to review; it must not silently mutate financial records.

### Tier C — Scale-dependent revenue

7. **Payment-related revenue**
   - Potentially earn a negotiated share or disclosed service fee through an eligible payment provider.
   - Do not assume a fixed percentage or enable fees until provider economics, settlement ownership, refunds, chargebacks, tax, and local legal requirements are verified.
   - Platform subscription payments and a tenant's customer payments remain separate domains.

8. **Partner application marketplace**
   - External developers can distribute approved integrations/add-ons.
   - Any commission, billing, payouts, moderation, security review, support boundary, and refund policy must be defined before launch.

9. **Hardware and operational supplies**
   - Optional referrals or resale for compatible POS devices, barcode scanners, and receipt printers.
   - Keep hardware procurement separate from software entitlement logic.

10. **Regulated financial referrals**
    - Financing, insurance, or payroll-finance referrals only through appropriate licensed partners and after legal review.
    - VeloraPlus should not lend its own funds or imply regulated status without authorization.

## 3. Recommended launch sequence

### Step 0 — Validate the business
- Interview target companies and select one initial segment.
- Validate willingness to pay with real pilots or paid onboarding.
- Measure time-to-value, support burden, retention, and gross margin.
- Do not build every revenue channel before proving a repeatable sale.

### Step 1 — Make current subscription commerce trustworthy
- Audit catalog readiness and ensure only implemented capabilities can be sold.
- Resolve dependency expansion consistently across selection, quote, invoice, subscription items, and entitlements.
- Show which items are selected directly, required automatically, already owned, included in a bundle, or billable.
- Avoid duplicate charging when dependencies are shared or already owned.
- Keep the subscription and subscription items as financial source of truth.
- Keep entitlements as a rebuildable authorization projection.
- Test cancellation, downgrade, refunds, trial expiry, upgrades, and tenant isolation.

### Step 2 — Sell implementation packages
- Define repeatable onboarding packages and scope boundaries.
- Track service delivery and support cost independently from subscription MRR.
- Avoid representing one-time setup fees as recurring revenue.

### Step 3 — Expand account revenue
- Add meaningful features and plan tiers after validating demand.
- Implement metering before selling per-seat or usage-based plans.
- Make plan limits visible and enforce them in backend services, not only in UI.

### Step 4 — Open partner and integration channels
- Establish partner qualification, security review, billing, customer support ownership, and revocation rules.
- Introduce third-party add-ons only after the core catalog, permissions, dependency rules, and billing lifecycle are reliable.

### Step 5 — Consider payments and AI
- Validate provider unit economics and legal constraints before payment-related fees.
- Add AI only where measurable customer value exceeds inference, monitoring, and support costs.

## 4. Unit economics dashboard

Track at minimum:

- Monthly recurring revenue (MRR) and annual recurring revenue (ARR).
- New MRR, expansion MRR, contraction MRR, and churned MRR.
- Active paying tenants and revenue per paying tenant.
- Gross margin after hosting, provider, support, and variable service costs.
- Customer acquisition cost (CAC) and CAC payback period.
- Logo churn and revenue churn.
- Trial-to-paid conversion and onboarding completion.
- Feature adoption and usage of paid add-ons.
- Payment success, refunds, chargebacks, and provider fees if payment-related revenue is introduced.
- AI cost per tenant and cost per successful workflow if AI is introduced.

MRR must exclude one-time onboarding fees, refundable deposits, taxes collected on behalf of authorities, and unearned payment volume.

## 5. Commercial guardrails

- No hidden mandatory fees.
- No double charging a dependency shared by multiple selected features.
- Existing owned capabilities must not be billed again unless the commercial contract explicitly defines a separate quantity or tier.
- Required dependencies and their costs must be visible before checkout.
- A bundle's commercial price must follow the approved bundle price; bundle membership determines grants but must not silently recalculate the bundle's price.
- Do not sell placeholders, coming-soon capabilities, or features without working backend enforcement.
- Do not remove customer business data when a subscription or feature ends.
- Never grant entitlements directly from a payment redirect; only verified payment transitions may activate paid capabilities.
- Keep Platform Billing (company pays VeloraPlus) separate from Tenant Payments (a company's customer pays that company).
- Customer data is not a revenue product; do not sell or disclose tenant data.

## 6. Current implementation facts from the audited main branch

- The central Module/Feature/Bundle catalog and price records exist.
- The platform has central subscriptions, subscription items, invoices, payment records, refunds, credits, audit events, and entitlement projection.
- The Marketplace service requests a purchase for one catalog item at a time.
- The dependency resolver traverses the graph and entitlement projection expands dependencies, but the inspected pricing/upgrade flow does not expand dependency items into billable subscription items.
- The catalog dependency record has a metadata JSON field, but the inspected resolver does not interpret commercial inclusion or billing semantics from it.
- Billing documents explicitly defer usage metering.
- Booking is the first production business module; CRM and ERP are future modules, not launch-ready sellable products.
- The documented Marketplace UI is not equivalent to a complete end-to-end self-serve checkout journey.
- Phase 8.6 documentation identifies remaining verification work. Commercial work must not claim the entire Dashboard/release gate is closed.

These findings were read from the GitHub main snapshot during the commercial audit. Re-check the branch and tests before implementing behavior.

## 7. Acceptance criteria for the commercial foundation

The foundation is ready only when:

1. Each sellable catalog item has an explicit readiness state and commercial owner.
2. A quote explains direct selections, transitive dependencies, existing entitlements, bundle inclusions, quantities, discounts, taxes, and final amount.
3. Shared dependencies are deduplicated in a deterministic way.
4. The billing rule for each dependency is explicit: included, separately billable, or already owned; no implicit assumption.
5. Quote, invoice lines, subscription items, and entitlement projection agree on the purchased capability set.
6. Activation requires verified payment for paid items.
7. Cancellation/downgrade preserves access until the contracted end date and retains business data.
8. Tests cover cycles, transitive and shared dependencies, already-owned dependencies, bundle overlaps, missing/inactive dependencies, pricing errors, payment failure, duplicate requests, concurrent upgrades, tenant isolation, and authorization.
9. Documentation and automated tests match delivered behavior.
10. No channel is described as launched until its provider, operational, legal, and support requirements are satisfied.
