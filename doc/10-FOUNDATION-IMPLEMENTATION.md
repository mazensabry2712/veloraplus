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

## Current Phase 1 progress

The central tenant registry, domain registry, memberships, tenant context, tenant connection manager, idempotent tenant database provisioning, baseline tenant migration verification, and local MySQL smoke-test path have now been implemented incrementally. See `doc/11-PHASE-1-TENANCY-FOUNDATION.md` for the current Phase 1 implementation status.

The remaining Phase 1 work is membership authorization plus local execution/verification of the new tenant request and isolation tests against the updated tenant schema.

## Next phase

Complete membership authorization and finish the Phase 1 integration/isolation verification before closing Phase 1.
