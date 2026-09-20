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
- doc/21-BACKEND-COMPLETION-AUDIT.md
- doc/22-VISUAL-IDENTITY.md

## Current implementation

Phase 1 — Tenancy Foundation is closed and verified.

Phase 2 — Identity, Authentication & RBAC is closed and verified.

Phase 3 — Module Catalog is closed and verified. It provides the central catalog schema, models, bundle/dependency services, and pricing catalog foundation.

Phase 4 — Entitlements is closed and verified.

Phase 5 — Billing is closed and verified. It provides the internal Billing domain with provider-neutral payment boundaries.

Phase 6 — Payments & Provider Adapters is closed and verified. Kashier is implemented as the first provider adapter behind provider-neutral payment boundaries.

Phase 7 — Booking core is implemented through 7.6: Services, Staff Availability, Appointments, Tenant Payments, Queue, and the Public Booking transaction are implemented and verified. SEO/Public Web is a locked cross-cutting capability; SEO-1 through SEO-4 are implemented and tested, while SEO-5 remains a production deployment/monitoring gate. The canonical contract is doc/19-SEO-AND-PUBLIC-WEB-ARCHITECTURE.md.

Phase 8 — Company Dashboard is in progress. 8.1 Dashboard Shell, 8.2 Company Foundation backend, 8.3 Booking workspace backend mutation layers, 8.4 Platform Billing workspace, and 8.5 Module Marketplace backend are implemented and verified. Company profile, branding, social, tax, and SEO settings have been reconciled with the documented contract. The first 8.6 Usage & Settings backend slice, including Company Preferences, is implemented. The user-verified regression immediately before the current Company read-screen frontend increment was 244 tests / 1306 assertions with a successful Vite production build. The current frontend branch now adds live Company Profile, Locations, Staff, Customers, Users, and Roles & Permissions read screens with permission-aware navigation, tenant-scoped queries, pagination, and shared Blade components. New read-screen tests are pending local execution after this increment.

The backend completion audit and Dashboard contract remain tracked in doc/20-PHASE-8-COMPANY-DASHBOARD.md and doc/21-BACKEND-COMPLETION-AUDIT.md.

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
