# VeloraPlus — Product Vision and Commercial Contract

## Decision

VeloraPlus will follow an **Outcome-First Modular Business Platform** strategy. This is a product direction and engineering contract, not a claim that every described capability is already implemented.

The platform should combine:
- the integration and shared business data of a unified business suite;
- modular purchasing at meaningful capability boundaries;
- a guided, outcome-based setup experience;
- transparent dependency-aware pricing;
- server-enforced access to paid capabilities;
- an ecosystem that can expand through vetted partners after the core commerce lifecycle is reliable.

The goal is not to reproduce Odoo screen-for-screen or compete with every ERP category immediately. The goal is to help a business assemble the smallest complete system that solves its current needs and expand without re-entering data or replacing the platform.

## 1. Start with the customer's outcome

The purchase journey should begin with what a company needs to achieve, not with a long list of technical switches.

Examples:
- sell products across a counter and online;
- manage leads, customers, and follow-ups;
- track stock, purchasing, and suppliers;
- run appointments and staff schedules;
- understand revenue, costs, and cash flow.

The platform can recommend applications and capabilities based on those outcomes. Recommendations are guidance; the customer must be able to inspect, adjust, and understand every selection before purchase.

## 2. Keep the product modular without making it confusing

- Applications are coherent business areas, not arbitrary collections of buttons.
- Features are meaningful capabilities that can be understood and priced.
- A base application license may grant the capabilities explicitly included in that license; feature-only purchase grants only the explicitly licensed capabilities and their required dependencies.
- The commercial contract for buying a whole Module versus individual Features must be consistent with entitlement enforcement and the UI. Do not imply granular selection while a Module entitlement grants all active Features unless that broader grant is clearly included in the price.
- Required dependencies are resolved by the server and shown before checkout.
- The platform must distinguish selected items, automatically required items, bundle-included items, already-owned items, and free requirements.
- Shared requirements are deduplicated. The same capability must not be charged twice for one covered quantity.
- Never sell a tiny switch solely to inflate the catalog. Group related capabilities into understandable packages where that improves customer outcomes.

## 3. Commercial contract

Every dependency edge must have an explicit commercial policy:
- included: the required capability is covered by the dependent item or approved bundle and is not separately priced;
- billable: the required capability is priced as a separate line unless already covered by an existing license or bundle;
- free: the capability is required and granted without a separate charge.

The default for existing dependencies is included for backward compatibility and to avoid introducing surprise charges. A catalog administrator must explicitly choose billable when the business contract requires a separate charge. An invalid policy must be rejected.

The stored policy is not sufficient by itself: quote generation, subscription item creation, invoices, and entitlement projection must all use the same normalized commercial selection. Until that integration is implemented and tested, dependency billing policies must not be advertised as live automated billing.

Additional rules:
- Subscription items and invoices remain the financial source of truth.
- Tenant entitlements remain a rebuildable authorization projection.
- Client-provided prices, dependency lists, ownership claims, and entitlement flags are never trusted.
- Paid capabilities activate only after verified payment.
- Trials must be clearly identified as trials; they are not proof of willingness to pay.
- Existing customer data is retained when access is downgraded or a feature is disabled.
- Platform Billing (company pays VeloraPlus) and Tenant Payments (a company's customer pays that company) remain separate domains.

## 4. Revenue strategy

### Launch
1. Recurring monthly/yearly subscriptions for implemented capabilities.
2. Paid onboarding, migration, configuration, and training with a clear scope.
3. Meaningful add-ons and limits only where backend enforcement exists.

### Expansion
4. API and integration plans with documented limits.
5. Qualified implementation partners and disclosed referral agreements.
6. AI-assisted analytics and automation when measured customer value exceeds operating cost.

### Scale-dependent options
7. Payment-related revenue negotiated with eligible providers after unit-economics and legal review.
8. A vetted third-party application marketplace with security, support, refunds, and payout policies.
9. Hardware referrals/resale for compatible operations.
10. Regulated financial referrals only through appropriate licensed partners.

These are candidate revenue channels, not committed launch features. Do not implement all of them before validating the core product with paying customers.

## 5. Business model validation

Choose one initial customer segment and validate the sales cycle before broad expansion. Track:
- paying tenants and recurring revenue;
- average revenue per paying tenant;
- gross margin after hosting, payment-provider, AI, and support costs;
- customer acquisition cost and payback;
- activation, onboarding completion, adoption, and retention;
- churn and expansion revenue;
- support burden and time-to-value.

One-time onboarding fees must not be reported as monthly recurring revenue. Payment volume is not platform revenue; only the platform's earned fee or share is revenue.

## 6. Product readiness rules

A catalog item is not sellable merely because it exists in the database. Before activation for purchase, verify:
- working end-to-end user journey;
- server-side authorization and tenant isolation;
- dependency availability and billing policy;
- a valid price for the selected currency, country, and billing cycle;
- correct quote, invoice, subscription item, and entitlement behavior;
- cancellation/downgrade/data-retention behavior;
- automated tests and operational support ownership.

CRM and ERP must be built as modules on the existing platform core, not separate applications. They must not be marketed as production-ready until their workflows and tests are complete.

## 7. Implementation order

1. Finish and verify the existing Phase 8 company-dashboard backend/release gate.
2. Complete the dependency-aware commerce contract, including whole-Module versus Feature entitlement semantics.
3. Make catalog selection, quotes, invoice lines, subscription items, and entitlements consistent.
4. Add a guided outcome-first setup and purchase experience using only implemented catalog items.
5. Validate the first market segment and paid onboarding offer with real customers.
6. Expand modules and revenue channels in response to measured demand.
7. Add metering before selling per-seat/usage plans; add partner/payment/AI channels only after their operational and legal gates.

## Acceptance principle

**No hidden requirements, no duplicate charges, no pretend features, and no commercial promise that the backend cannot enforce.**