# VeloraPlus Documentation

## Purpose

This folder is the official product, architecture, business-rules, database, billing, tenancy, and MVP planning reference for the new VeloraPlus platform.

The project is being rebuilt from a clean Laravel foundation. The goal is not to recreate a standalone booking application. The goal is to build a reusable SaaS Multi-Tenant Modular Business Platform where Booking is the first production module and CRM/ERP can be added later without rebuilding the platform core.

## Source of truth

For product and architecture decisions, this doc folder is the source of truth unless a newer explicit project decision supersedes a documented rule.

Implementation must follow these documents:

1. 01-PRODUCT-BUSINESS-RULES.md
2. 02-SAAS-TENANCY-IDENTITY.md
3. 03-MODULE-FEATURE-ENTITLEMENT.md
4. 04-BILLING-PRICING-PAYMENTS.md
5. 05-DATABASE-ARCHITECTURE.md
6. 06-APPLICATION-ARCHITECTURE.md
7. 07-MVP-ROADMAP-AND-ACCEPTANCE.md
8. 08-STACK-AND-LIBRARIES.md
9. 09-SCALABILITY-AND-PERFORMANCE.md
10. 10-FOUNDATION-IMPLEMENTATION.md
11. 11-PHASE-1-TENANCY-FOUNDATION.md
12. 12-PHASE-2-IDENTITY-AUTH-RBAC.md
13. 13-PHASE-3-MODULE-CATALOG.md

## Locked decisions

- Product type: SaaS, multi-tenant, modular.
- One VeloraPlus platform serves many companies.
- Every company is a tenant with its own database.
- Default company address: {slug}.velora.com.
- Custom domains are a future capability and must be supported by the architecture.
- One platform account can own or belong to more than one company.
- Company data is isolated from every other company.
- Customer accounts may exist at platform level, while customer business profiles remain company-specific.
- A company can buy a complete Module or individual Features.
- Bundles are supported and may have a discount.
- Required dependencies may be auto-activated.
- A newly purchased Feature becomes active immediately after confirmed payment.
- Feature removal/downgrade takes effect at the next billing period unless an explicit administrative/business rule allows an earlier change.
- Disabled Features never cause their business data to be silently deleted.
- Trial: 14 days.
- Billing cycles: monthly and yearly.
- Initial VeloraPlus subscription payment provider: Kashier.
- Payment providers are isolated behind an internal gateway abstraction.
- Company-to-VeloraPlus billing is separate from customer-to-company payments.
- Company customer payments are designed around a merchant/payment-provider account belonging to the company.
- Company currency, timezone, locale, and tax settings are tenant-specific.
- Branches/locations are supported from the MVP architecture.
- Frontend: Blade + Tailwind CSS + Alpine.js + Vanilla JavaScript + Vite.
- Global performance: stateless application nodes, horizontal scaling, Redis-backed shared state/queues, tenant-aware caching, async processing, CDN/edge readiness, observability, and load testing.
- Backend: Laravel 13 + PHP 8.4 target + MySQL 8.4 + Redis.
- Architecture style: modular monolith, not microservices.
- Booking is the first business module.
- CRM and ERP are future modules built on the same Core.
- Core entities must be reusable across modules; duplicate Staff/Customer records are not created just because another module is activated.

## Engineering principle

Build the platform core first, then business modules.

~~~
Architecture
    ↓
Central Registry + Tenant Infrastructure
    ↓
Identity / Authentication / RBAC
    ↓
Company / Staff / Customer
    ↓
Modules / Features / Dependencies
    ↓
Entitlements
    ↓
Billing / Pricing / Payments
    ↓
Booking MVP
    ↓
Admin + Customer Portal
    ↓
Production QA
    ↓
CRM
    ↓
ERP
~~~

## Current repository note

Foundation and Tenancy are implemented. Phase 2 Identity / Authentication / RBAC is closed and verified. Phase 3 is the Module Catalog and its central schema/services are now implemented; local/CI verification is the remaining gate.

The repository documentation and implementation order are kept aligned with the current phase so that no business module is started against an incomplete platform core.

## Non-negotiable rules

- Do not build CRM or ERP as separate applications.
- Do not duplicate Core Customer, Staff, or Account identities per Module.
- Do not put entitlement decisions only in Blade or JavaScript. Backend authorization is authoritative.
- Do not put dynamic billing rules in Controllers.
- Do not use floating point for money calculations.
- Do not couple the Billing domain directly to a single payment provider.
- Do not create cross-tenant foreign keys between tenant databases and the central database.
- Do not permanently delete business data only because a paid Feature was disabled.

## Global Scale Requirement

VeloraPlus is intended for global usage. The platform must be engineered from the beginning for very fast response times, high concurrency, tenant isolation, and measurable reliability.

Detailed rules live in doc/09-SCALABILITY-AND-PERFORMANCE.md.
