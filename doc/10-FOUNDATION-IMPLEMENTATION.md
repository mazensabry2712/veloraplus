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

### Intentionally not implemented yet

- Tenant database provisioning.
- Tenant domain/subdomain resolution.
- Memberships.
- Roles/permissions package.
- Module catalog.
- Entitlements.
- Billing.
- Kashier.
- Booking.

Those belong to later phases and should not be duplicated or approximated inside Foundation.

## Implementation rule

Every completed phase must update:

1. implementation code;
2. tests;
3. relevant architecture/business documentation;
4. this implementation status document.

A phase is not complete because code was committed only.

## Next phase

Phase 1 continues with the central platform registry and multi-tenant infrastructure after the Foundation changes pass locally against MySQL 8.4.
