# VeloraPlus — Foundation Implementation

## Purpose

This document tracks what has actually been implemented in the repository. It is not a replacement for the architecture documents.

## Phase 0 status

### Implemented

- Laravel 13 skeleton retained as the framework baseline.
- PHP 8.4 remains the project target.
- Global scalability/performance requirements documented.
- Platform identity naming established around `PlatformAccount`.
- Central platform account table uses ULIDs.
- Authentication configuration points to `PlatformAccount`.
- Central password-reset and session storage remains outside tenant databases.
- VeloraPlus platform configuration is centralized in `config/velora.php`.
- Local environment example is aligned with MySQL and Redis-oriented runtime settings.
- Foundation tests verify platform identity configuration and persistence behavior.
- Local `php artisan migrate` verified successfully against MySQL and created the central `veloraplus` database.
- Central migrations for platform accounts, cache, and jobs are currently applying successfully.

### Intentionally not implemented yet

- Roles/permissions package.
- Module catalog.
- Entitlements.
- Billing.
- Kashier.
- Booking.

These belong to later phases and should not be duplicated or approximated inside Foundation.

## Implementation rule

Every completed phase must update:

1. implementation code;
2. tests;
3. relevant architecture/business documentation;
4. this implementation status document.

A phase is not complete because code was committed only.

## Phase 1 status — closed

The tenancy foundation is complete and verified. The implementation includes the central tenant/domain/membership registries, dedicated tenant database provisioning, tenant routing/context, tenant-owned Company Settings/Locations/Staff/Customers, active membership authorization, cross-tenant isolation coverage, and production-style request-context cleanup.

Verification completed:

- Local `php artisan test`: **16 passed (59 assertions)**.
- GitHub Actions Backend Tests for commit `0712ea36fbe35656bcdfd44bdb31540d989ea169`: **success**.
- Existing MySQL tenant `velora-clinic`: `active` + `ready`.
- Tenant baseline tables verified: `tenant_runtime`, `company_settings`, `locations`, `staff`, `customers`.
- Tenant runtime schema version verified: `1.1`.
- Default tenant company settings verified: 6 entries.

See `doc/11-PHASE-1-TENANCY-FOUNDATION.md` for the detailed Phase 1 record.

## Next phase

Phase 2 starts with platform Identity, Authentication, and RBAC. This phase must preserve the existing tenant isolation boundary and reuse the existing `PlatformAccount`, memberships, Staff, and Customer identity model.
