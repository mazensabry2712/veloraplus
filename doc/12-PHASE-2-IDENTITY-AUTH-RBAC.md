# VeloraPlus — Phase 2 Identity, Authentication & RBAC

## Status

**IN PROGRESS — architecture and dependency preparation complete; package installation is the next implementation gate.**

Phase 2 establishes the platform authentication boundary and tenant-scoped authorization model without changing the Phase 1 tenancy contract.

## Goals

- Authenticate one central PlatformAccount.
- Keep authentication state in the central/control database.
- Support registration, login, logout, email verification, and password reset.
- Reuse one account across multiple tenants.
- Scope Roles and Permissions by Tenant.
- Keep Membership authorization separate from Role/Permission authorization.
- Prevent role/permission state from leaking between tenant contexts.
- Keep authorization metadata central so it can safely reference the central PlatformAccount and Tenant identities.

## Canonical identity flow

~~~
Browser Request
      ↓
Tenant Resolver
      ↓
Tenant Context
      ↓
Authenticated PlatformAccount
      ↓
Active TenantMembership
      ↓
Set Permission Team = Current Tenant
      ↓
Role / Permission Check
      ↓
Business Action
~~~

tenant and tenant.member remain separate middleware boundaries.

- tenant resolves and connects the company database.
- tenant.member verifies active membership and establishes the Spatie permission team.
- Permission/role middleware or Policies execute only after the tenant permission team is established.

## Authentication

Laravel Fortify is the selected authentication backend.

Phase 2 initial Fortify feature set:

- Registration.
- Login/logout.
- Password reset.
- Email verification.

Two-factor authentication is intentionally a later security slice after the base authentication flow is stable.

The authenticated model remains App\Models\PlatformAccount on the central connection.

## RBAC model

Spatie Laravel Permission is used with Teams enabled.

The package team key is renamed from team_id to tenant_id so the authorization schema matches the platform domain language.

### Central tables

~~~
permissions
roles
model_has_permissions
model_has_roles
role_has_permissions
~~~

All five tables live in the central database.

Tenant business tables remain in each dedicated tenant database.

No tenant database receives a cross-database foreign key to the RBAC tables.

## Team isolation

Each Role belongs to exactly one Tenant in VeloraPlus.

A Role name may therefore exist independently in multiple tenants:

~~~
Tenant A → owner
Tenant B → owner
~~~

The same PlatformAccount can have different role assignments in each tenant.

The permission team is derived from the resolved tenant context, never from an arbitrary request field.

At the end of the request, the permission team must be reset to null to avoid state leakage in long-running workers.

When permission team context changes inside a request, cached roles and permissions relations on the account must be unset before authorization checks.

## ULID policy

VeloraPlus uses ULIDs for platform identities. Therefore:

- Role IDs use ULIDs.
- Permission IDs use ULIDs.
- Role/Permission pivot foreign keys use ULIDs.
- model_id uses a ULID-compatible column.
- tenant_id uses the central tenants.id ULID.

The package's migration must be customized for these identifier types before the first RBAC migration is run.

## Default roles

Initial tenant roles:

| Role | Intended scope |
|---|---|
| owner | Full company control |
| admin | Broad operational administration |
| manager | Operational management |
| staff | Day-to-day operational access |
| viewer | Read-only operational access |

These are bootstrap roles, not the final limit on custom tenant roles.

## Core permissions

Initial platform-core permission vocabulary:

- company.view
- company.update
- members.view
- members.manage
- staff.view
- staff.manage
- customers.view
- customers.manage
- locations.view
- locations.manage
- settings.view
- settings.manage

Booking-specific permissions are intentionally deferred until the Booking module is implemented.

## Membership vs Role

Membership answers:

> Is this Platform Account allowed to enter this Tenant workspace?

Role/Permission answers:

> What is this account allowed to do inside this Tenant?

Both checks are required for protected tenant workspace actions.

tenant_memberships.role_key remains the membership's primary role identifier for bootstrap/UI context. Spatie assignments are the authoritative permission source once RBAC is active.

## Tenant creation

When a new Tenant is created:

1. The central Tenant record is created.
2. The primary domain and Owner Membership are created.
3. The dedicated tenant database is provisioned.
4. The tenant's default RBAC roles are created in the central database.
5. The owner's PlatformAccount receives the owner role for that tenant.
6. The tenant can then enter protected workspace routes.

The process must be idempotent.

## Existing tenant migration

Existing tenants, including velora-clinic, require an explicit RBAC bootstrap after the package migration is installed.

The bootstrap must:

- create missing default roles for each existing tenant;
- create missing core permissions;
- sync default role permissions;
- assign owner to each active Owner Membership;
- remain safe to run more than once.

## Security requirements

- Suspended Platform Accounts cannot authenticate.
- Passwords are hashed through Laravel's password hashing.
- Login and password-recovery endpoints are rate limited.
- Permission decisions are server-side.
- Tenant identity comes from the trusted host/domain registry.
- A user cannot gain access to another tenant by changing tenant_id in a request.
- Permission team context is always reset after the request.
- Secrets are never stored in source code.

## Test requirements

Phase 2 must include coverage for:

### Authentication

- login page renders;
- active account can authenticate;
- invalid credentials are rejected;
- suspended account is rejected;
- registration creates a PlatformAccount;
- email verification is required where protected routes use verified;
- password reset request and password reset flow work.

### Tenant RBAC

- same account can hold different roles in different tenants;
- permissions in Tenant A do not apply in Tenant B;
- active membership is required;
- non-member is forbidden even if another tenant grants the account permissions;
- permission team is established from tenant context;
- permission team is cleared after the request.

### Negative security tests

- forged tenant identifier does not switch permission context;
- role assignment for Tenant A cannot authorize Tenant B;
- a suspended account cannot access tenant workspace routes;
- missing permission is forbidden;
- membership and permission checks remain independent.

## Dependency gate

The selected versions verified for the current date are:

- laravel/fortify 1.39.0;
- spatie/laravel-permission 8.3.0.

Laravel 13 compatibility is documented for both packages. The repository must commit the resulting Composer lockfile after installation.

## Exit criteria

Phase 2 closes only when:

- Fortify is installed and configured.
- PlatformAccount authentication works.
- Email verification and password reset are tested.
- Spatie Permission Teams is installed with tenant_id.
- ULID-compatible RBAC migrations pass on MySQL and test SQLite.
- Default roles and permissions are bootstrapped.
- Owner role is assigned per tenant.
- Tenant permission context is established and cleared safely.
- Cross-tenant RBAC negative tests pass.
- Full local test suite passes.
- GitHub Actions passes.
- Documentation is synchronized with the final implementation.

## Next after Phase 2

Phase 3 is the Module Catalog:

~~~
Modules
  ↓
Features
  ↓
Module ↔ Feature
  ↓
Dependencies
  ↓
Bundles
  ↓
Catalog / Pricing
~~~

Entitlements remain a separate phase after the catalog.
