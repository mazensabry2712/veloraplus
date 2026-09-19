# VeloraPlus — Foundation Implementation

## Purpose

This document tracks what has actually been implemented in the repository. It is not a replacement for the architecture documents.

## Phase 0 status

### Implemented

- Laravel 13 skeleton retained as the framework baseline.
- PHP 8.4 remains the project target.
- Global scalability/performance requirements documented.
- Platform identity naming established around PlatformAccount.
- Central platform account table uses ULIDs.
- Authentication configuration points to PlatformAccount.
- Central password-reset and session storage remains outside tenant databases.
- VeloraPlus platform configuration is centralized in config/velora.php.
- Local environment example is aligned with MySQL and Redis-oriented runtime settings.
- Foundation tests verify platform identity configuration and persistence behavior.
- Local php artisan migrate verified successfully against MySQL and created the central veloraplus database.
- Central migrations for platform accounts, cache, and jobs are currently applying successfully.

## Phase 1 status — closed

The tenancy foundation is complete and verified. The implementation includes the central tenant/domain/membership registries, dedicated tenant database provisioning, tenant routing/context, tenant-owned Company Settings/Locations/Staff/Customers, active membership authorization, cross-tenant isolation coverage, and production-style request-context cleanup.

Verification completed:

- Local php artisan test: 16 passed (59 assertions).
- GitHub Actions Backend Tests for commit 0712ea36fbe35656bcdfd44bdb31540d989ea169: success.
- Existing MySQL tenant velora-clinic: active + ready.
- Tenant baseline tables verified: tenant_runtime, company_settings, locations, staff, customers.
- Tenant runtime schema version verified: 1.1.
- Default tenant company settings verified: 6 entries.

See doc/11-PHASE-1-TENANCY-FOUNDATION.md for the detailed Phase 1 record.

## Phase 2 status — closed

Phase 2 Identity, Authentication, and RBAC is complete and verified.

Implemented:

- Laravel Fortify authentication around PlatformAccount.
- Registration, login/logout, password reset, and email verification.
- Suspended-account authentication protection.
- Spatie Laravel Permission Teams with tenant_id.
- ULID-compatible central RBAC schema and custom Role/Permission models.
- Tenant permission-team setup and cleanup.
- Idempotent default role/permission bootstrap.
- Membership role synchronization.
- New-tenant owner-role bootstrap.
- Existing-tenant RBAC bootstrap command.

Verification completed:

- Local suite: 29 tests passed with 112 assertions before the catalog phase began.
- GitHub Actions for the phase implementation commits: successful after the final RBAC fixes.
- Existing velora-clinic RBAC bootstrap: successful.
- velora-clinic roles: owner, admin, manager, staff, viewer.
- velora-clinic permissions: 12.
- Owner membership resolved to the owner Spatie role under the tenant permission team.
- Permission team context explicitly reset to null after verification.

See doc/12-PHASE-2-IDENTITY-AUTH-RBAC.md for the detailed Phase 2 record.

## Phase 3 status — closed

The Module Catalog is implemented as a central platform domain.

Implemented:

- central Module, Feature, Bundle, Dependency, and Catalog Price schemas;
- ULID Eloquent models and relationships;
- lifecycle status validation;
- bundle composition;
- circular dependency validation;
- country/currency/billing-cycle catalog price resolution;
- integer minor-unit price representation;
- core-module pricing protection;
- catalog price effective-window and overlap protection.

Verification completed:

- Local migration against the current MySQL environment succeeded.
- Laravel Pint passed.
- Local test suite: **38 tests passed with 135 assertions**.
- git diff --check passed.
- git status clean and synchronized with origin/main.
- GitHub Actions for commit 98bbb45a8d37f1ffbc9b00ec86c2047fde7ada84: success.

See doc/13-PHASE-3-MODULE-CATALOG.md for the detailed Phase 3 record.

## Phase 4 status — closed / verified

Phase 4 Entitlements is implemented as the tenant-level authorization projection between the central Catalog and future Billing.

Implemented:

- central tenant_entitlements schema;
- entitlement lifecycle/source enums;
- TenantEntitlement model and factory;
- CatalogDependencyResolver for transitive dependency traversal;
- EntitlementService for grant, revoke, scheduled removal, capability checks, limits, status, and projection rebuild;
- Module-to-Feature entitlement inheritance;
- dependency-aware access checks;
- deterministic entitlement source precedence;
- centralized entitled middleware;
- @entitled, @featureEntitled, and @moduleEntitled Blade helpers;
- Phase 4 feature test coverage.

Verification completed:

- central entitlement migration succeeded;
- Pint passed;
- full local suite: **50 tests passed with 166 assertions**;
- `git diff --check` passed;
- local `main` is synchronized with `origin/main`;
- GitHub Actions for final commit `b67009f5b0082a0a9c978cfee5a87af8d8c81703`: success.

The local working tree still reports an untracked `public/logo.png`; it was not modified or added by this phase.

See doc/15-PHASE-4-ENTITLEMENTS.md for the detailed Phase 4 contract and acceptance criteria.

## Cross-cutting Custom Domain architecture status

The Phase 1 tenancy implementation already contains the central `tenant_domains` registry and request-host-based tenant resolution foundation.

The Custom Domain business/technical contract is now explicitly documented in `doc/14-CUSTOM-DOMAIN-ARCHITECTURE.md`.

Current status:

- domain registry foundation: implemented;
- default tenant hostname: implemented;
- verified-domain requirement: architectural rule;
- customer DNS onboarding flow: documented;
- SSL/TLS lifecycle: documented;
- monetization as a Catalog Feature: documented;
- provider-neutral edge/domain boundary: documented;
- production custom-domain infrastructure: deferred until the Tenancy + Entitlements + Billing dependencies are ready.

This documentation update does not claim that custom-domain provisioning or SSL automation is already implemented.

## Phase 5 status — closed / verified

Phase 5 Billing has been implemented on the central platform side.

Implemented:

- subscriptions and subscription items;
- deterministic pricing with monthly/yearly cycles;
- 14-day trial;
- immutable platform invoices and invoice lines;
- platform payments;
- upgrades, scheduled downgrades, and cancellation lifecycle;
- renewal preparation;
- refunds and credits;
- billing audit trail;
- billing-driven entitlement projection;
- provider-neutral platform/tenant payment contracts.

No Kashier implementation was added to Core Billing. Phase 6 will add provider adapters.

Verification completed:

- local `composer install` completed;
- billing migration succeeded;
- Pint passed;
- full local suite: **65 tests passed with 216 assertions**;
- `git diff --check` passed;
- local working tree is clean and synchronized with `origin/main`;
- GitHub Actions for final Phase 5 hardening commit `ba37072486b932b93d65783b96ed2aa8b9d17468`: success.

No Kashier implementation was added to Core Billing. Phase 6 will add provider adapters.

See doc/16-PHASE-5-BILLING.md for the full contract and verification record.


## Phase 7 status — in progress

Phase 7 has started with the Services/database foundation slice. The tenant schema now supports the first Booking entity, service application logic is covered by tests, Booking service permissions are part of tenant RBAC, and ready tenant databases apply pending tenant migrations during provisioning/re-provisioning.

## Phase 6 status — closed / verified

Phase 6 Payments & Provider Adapters has been implemented with a provider-neutral gateway boundary and the first concrete Kashier adapter.

Implemented:

- Platform Payment Gateway and Tenant Payment Gateway boundaries;
- PaymentGatewayManager provider registry;
- capability-specific checkout, verification, refund, webhook, and transaction lookup contracts;
- Kashier hosted Payment Session adapter;
- Kashier payment lookup and refund adapter;
- Kashier webhook signature verification;
- central webhook idempotency storage;
- verified Platform Payment activation through the existing Billing services;
- provider-neutral tenant payment boundary.

No live credentials were committed. Tenant business payment flows remain unimplemented until their Booking/CRM/ERP domains consume the shared payment layer.

GitHub Actions for implementation commit `b36fc620a859a20d7bacc6839a3f97cbe190732a` passed.

Verification completed after pulling the latest `main`:

- `composer install` completed without dependency changes.
- payment/webhook migration succeeded.
- Pint passed.
- full local suite: **72 tests passed with 238 assertions**.
- `git diff --check` passed.
- working tree is clean and synchronized with `origin/main`.

See doc/17-PHASE-6-PAYMENTS.md for the detailed Phase 6 record.
