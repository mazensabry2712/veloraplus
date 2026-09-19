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
- Initial VeloraPlus subscription payment provider: Kashier.
- Global-scale performance and horizontal scaling are architecture requirements.

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

## Current implementation

Phase 1 — Tenancy Foundation is closed and verified.

Phase 2 — Identity, Authentication & RBAC is closed and verified.

Phase 3 — Module Catalog is closed and verified. It provides the central catalog schema, models, bundle/dependency services, and pricing catalog foundation.

The next implementation stage is Phase 4 — Entitlements.

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
