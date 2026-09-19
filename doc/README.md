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
14. 14-CUSTOM-DOMAIN-ARCHITECTURE.md
15. 15-PHASE-4-ENTITLEMENTS.md
16. 16-PHASE-5-BILLING.md
17. 17-PHASE-6-PAYMENTS.md
18. 18-PHASE-7-BOOKING.md
19. 19-SEO-AND-PUBLIC-WEB-ARCHITECTURE.md

## Locked decisions

- Product type: SaaS, multi-tenant, modular.
- One VeloraPlus platform serves many companies.
- Every company is a tenant with its own database.
- Default company address: {slug}.velora.com.
- Custom domains are a first-class cross-cutting capability: the architecture supports verified custom hostnames, tenant routing, SSL/TLS lifecycle, and future edge-provider integration.
- A Custom Domain is represented commercially as a catalog Feature; whether it is separately priced or included in a Bundle/plan is catalog configuration, not hard-coded application logic.
- The customer owns and controls the domain registration; VeloraPlus provides onboarding instructions, verification, routing integration, and platform-side lifecycle management.
- The application remains provider-neutral at the domain boundary; an edge/SSL provider can be selected without changing tenant/business logic.
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
- Payment architecture is provider-neutral; Platform Billing and Tenant Payments are separate payment domains.
- Kashier is the first provider adapter implemented in Phase 6, not a Core Billing dependency.
- Payment providers are isolated behind an internal gateway abstraction.
- Company-to-VeloraPlus billing is separate from customer-to-company payments.
- Company customer payments are designed around a merchant/payment-provider account belonging to the company.
- Company currency, timezone, locale, and tax settings are tenant-specific.
- Branches/locations are supported from the MVP architecture.
- Frontend: Blade + Tailwind CSS + Alpine.js + Vanilla JavaScript + Vite.
- Global performance: stateless application nodes, horizontal scaling, Redis-backed shared state/queues, tenant-aware caching, async processing, CDN/edge readiness, observability, and load testing.
- Backend: Laravel 13 + PHP 8.4 target + MySQL 8.4 + Redis.
- Public Web: server-rendered Blade with a centralized, tenant-aware SEO architecture.
- Architecture style: modular monolith, not microservices.
- Booking is the first business module.
- CRM and ERP are future modules built on the same Core.
- Public URLs, crawlability, canonicalization, sitemap, robots, and structured data are governed centrally and remain tenant-isolated.
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
Billing
    ↓
Payments / Provider Adapters
    ↓
Booking MVP Core
    ↓
Public Web + SEO Foundation
    ↓
Public Booking
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

Foundation and Tenancy are implemented. Phase 2 Identity / Authentication / RBAC is closed and verified. Phase 3 Module Catalog is closed and verified.

Phase 4 — Entitlements is closed and verified. Phase 5 — Billing is closed and verified. Phase 6 — Payments & Provider Adapters is closed and verified; Kashier is implemented as the first provider adapter behind provider-neutral payment boundaries.

Phase 7 — Booking is in progress. Booking core slices 7.1 Services, 7.2 Staff Availability, 7.3 Appointments, 7.4 Tenant Payments, 7.5 Queue, and 7.6 Public Booking are implemented and verified. Phase 7 remains open only for the production SEO-5 gate and the final Phase 7 closure/verification step.

SEO/Public Web is a locked cross-cutting capability documented in doc/19-SEO-AND-PUBLIC-WEB-ARCHITECTURE.md. SEO-1 through SEO-4 are implemented and tested; SEO-5 production operations remain pending. The transactional Public Booking route is complete and remains non-indexable. Custom Domain architecture is explicitly locked in doc/14-CUSTOM-DOMAIN-ARCHITECTURE.md; its infrastructure implementation remains a later cross-cutting delivery.

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
