# VeloraPlus

VeloraPlus is a global SaaS, multi-tenant, modular business platform.

The platform is being rebuilt from a clean Laravel 13 foundation. Booking is the first production business module, followed by future CRM and ERP modules on the same shared platform core.

## Architecture

- Multi-tenant SaaS.
- Dedicated database per company/tenant.
- Central platform/control database.
- One platform account can belong to multiple companies.
- Modular monolith architecture.
- Backend-authoritative authorization and entitlements.
- Monthly and yearly composable billing.
- Provider-neutral payment architecture with separate Platform Billing and Tenant Payment gateways.
- Kashier is the initial provider adapter for the payment layer, not a Core Billing dependency.
- Global-scale performance and horizontal scaling are architecture requirements.
- Custom domains are architecturally supported and designed as a tenant-aware, sellable catalog Feature.

## Backend baseline

- PHP 8.4
- Laravel 13
- MySQL 8.4
- Redis

Frontend is intentionally Blade + Tailwind CSS + Alpine.js + Vanilla JavaScript + Vite.

## Documentation

The official project documentation is in doc/README.md.

Important references:

- doc/01-PRODUCT-BUSINESS-RULES.md
- doc/02-SAAS-TENANCY-IDENTITY.md
- doc/03-MODULE-FEATURE-ENTITLEMENT.md
- doc/04-BILLING-PRICING-PAYMENTS.md
- doc/05-DATABASE-ARCHITECTURE.md
- doc/06-APPLICATION-ARCHITECTURE.md
- doc/07-MVP-ROADMAP-AND-ACCEPTANCE.md
- doc/08-STACK-AND-LIBRARIES.md
- doc/09-SCALABILITY-AND-PERFORMANCE.md
- doc/10-FOUNDATION-IMPLEMENTATION.md
- doc/11-PHASE-1-TENANCY-FOUNDATION.md
- doc/12-PHASE-2-IDENTITY-AUTH-RBAC.md
- doc/13-PHASE-3-MODULE-CATALOG.md
- doc/14-CUSTOM-DOMAIN-ARCHITECTURE.md
- doc/15-PHASE-4-ENTITLEMENTS.md
- doc/16-PHASE-5-BILLING.md
- doc/17-PHASE-6-PAYMENTS.md
- doc/18-PHASE-7-BOOKING.md
- doc/19-SEO-AND-PUBLIC-WEB-ARCHITECTURE.md
- doc/20-PHASE-8-COMPANY-DASHBOARD.md

## Current implementation

Phase 1 — Tenancy Foundation is closed and verified.

Phase 2 — Identity, Authentication & RBAC is closed and verified.

Phase 3 — Module Catalog is closed and verified. It provides the central catalog schema, models, bundle/dependency services, and pricing catalog foundation.

Phase 4 — Entitlements is closed and verified.

Phase 5 — Billing is closed and verified. It provides the internal Billing domain with provider-neutral payment boundaries.

Phase 6 — Payments & Provider Adapters is closed and verified. Kashier is implemented as the first provider adapter behind provider-neutral payment boundaries.

Phase 7 — Booking core is implemented through 7.6: Services, Staff Availability, Appointments, Tenant Payments, Queue, and the Public Booking transaction are implemented and verified. SEO/Public Web is a locked cross-cutting capability; SEO-1 through SEO-4 are implemented and tested, while SEO-5 remains a production deployment/monitoring gate. The canonical contract is doc/19-SEO-AND-PUBLIC-WEB-ARCHITECTURE.md.

Phase 8 — Company Dashboard is in progress. 8.1 Dashboard Shell, 8.2 Company Foundation, and 8.3 Booking workspace backend mutation layers are implemented and verified for the documented Company, People, Booking Services, Availability, Appointments, Queue, and Tenant Payments capabilities with tenant isolation, RBAC, and entitlement boundaries. 8.4.1 Platform Billing access, 8.4.2 Platform Billing checkout, and 8.4.3 Platform Billing financial history/actions are implemented and locally verified. The current local regression suite is green at 218 tests / 1129 assertions, including 26 dedicated Platform Billing/Billing tests. 8.4.4 Subscription Changes is now in implementation with Dashboard upgrade/downgrade actions and coverage. Frontend listing/forms remain for the later Dashboard UI pass.

## Development principle

Every phase must follow:

    Documented decision
        ↓
    Implementation
        ↓
    Tests
        ↓
    Review
        ↓
    Documentation update
        ↓
    Commit

No business module should bypass the platform core.
