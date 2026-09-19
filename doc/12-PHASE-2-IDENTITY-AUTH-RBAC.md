# VeloraPlus — Phase 2 Identity, Authentication & RBAC

## Status

**CLOSED / VERIFIED — authentication and tenant-scoped RBAC implementation is complete.**

Phase 2 establishes the platform authentication boundary and tenant-scoped authorization model without changing the Phase 1 tenancy contract.

## Completed implementation slices

### Authentication

- PlatformAccount is the central authentication model.
- Fortify is registered as an application provider.
- Registration, login/logout, password reset, and email verification are enabled.
- Suspended accounts are rejected during authentication.
- Fortify views are mapped to minimal Blade templates so the backend routes work before the final frontend pass.
- All published Fortify actions were migrated away from the removed App\Models\User class.

### Tenant-scoped RBAC

- Spatie Laravel Permission Teams is enabled with tenant_id.
- Custom ULID Role and Permission models are configured.
- PlatformAccount uses HasRoles.
- tenant.member establishes the active permission team from TenantContext and clears it in a finally block.
- Cached roles and permissions relations are unset when the team context changes.
- Suspended authenticated accounts are blocked from tenant workspace middleware.
- Default roles and permissions are bootstrapped idempotently.
- New tenant creation runs RBAC bootstrap after tenant database provisioning.
- Existing tenants can be bootstrapped with php artisan tenants:bootstrap-rbac.

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
- Two-factor authentication is intentionally deferred.
- Passkeys are intentionally deferred.

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

At the end of the request, the permission team is reset to null to avoid state leakage in long-running workers.

When the permission team changes inside a request, cached roles and permissions relations on the account are unset before authorization checks.

## Default roles and permission matrix

Initial tenant roles:

| Role | Intended scope |
|---|---|
| owner | Full company control |
| admin | Broad operational administration |
| manager | Operational management |
| staff | Day-to-day operational access |
| viewer | Read-only operational access |

Initial permissions:

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

Default assignments:

| Role | Permissions |
|---|---|
| owner | all 12 |
| admin | all 12 |
| manager | company.view, members.view, staff.view/manage, customers.view/manage, locations.view/manage, settings.view |
| staff | company.view, staff.view, customers.view/manage, locations.view |
| viewer | all six *.view permissions |

These are bootstrap roles, not the final limit on custom tenant roles. Booking service permissions are introduced with Phase 7 and will expand slice-by-slice as additional Booking capabilities become protected.

## Membership vs Role

Membership answers:

> Is this Platform Account allowed to enter this Tenant workspace?

Role/Permission answers:

> What is this account allowed to do inside this Tenant?

Both checks are required for protected tenant workspace actions.

tenant_memberships.role_key remains the membership role identifier for bootstrap/UI context. Spatie assignments are the authoritative permission source once RBAC is active.

## Tenant creation

When a new Tenant is created:

1. The central Tenant record is created.
2. The primary domain and Owner Membership are created.
3. The dedicated tenant database is provisioned.
4. Default tenant RBAC roles and permissions are created.
5. Every active membership with a recognized default role_key receives the matching tenant role.
6. The tenant can then enter protected workspace routes.

The RBAC bootstrap operation is idempotent and safe to repeat.

## Existing tenant migration

Existing tenants, including velora-clinic, can be bootstrapped with:

~~~powershell
php artisan tenants:bootstrap-rbac
~~~

A single tenant can be targeted by ULID or slug:

~~~powershell
php artisan tenants:bootstrap-rbac velora-clinic
~~~

## Security requirements

- Suspended Platform Accounts cannot authenticate.
- Suspended authenticated accounts cannot pass tenant membership middleware.
- Passwords are hashed through Laravel's password hashing.
- Login is rate limited by Fortify/Laravel.
- Permission decisions are server-side.
- Tenant identity comes from the trusted host/domain registry.
- A user cannot gain access to another tenant by changing a request tenant_id.
- Permission team context is always reset after the request.
- Secrets are never stored in source code.

## Test coverage added

Authentication coverage includes:

- authentication views;
- successful and failed login;
- suspended account rejection;
- registration and verification notification;
- password reset notification;
- password reset completion;
- email verification.

RBAC coverage includes:

- idempotent role/permission bootstrap;
- different roles for the same account across tenants;
- tenant team setup and cleanup;
- membership/suspended-account negative cases;
- automatic owner-role bootstrap during new tenant creation.


## SEO / Private Route integration

Phase 2 authentication creates private/public boundary implications for SEO.

Authentication pages and tenant workspace pages are not public SEO landing pages.

When the public SEO layer is implemented:

- login/register/account/password-reset and authenticated workspace routes receive an explicit non-indexing policy;
- authentication and authorization state must never be rendered into public structured data;
- SEO metadata must not expose tenant membership, roles, permissions, customer identity, or private business data;
- public routes must not require tenant membership merely to be crawlable.

The detailed public/private indexing contract is defined in `doc/19-SEO-AND-PUBLIC-WEB-ARCHITECTURE.md`.

## Verification gate

Phase 2 closing verification was completed with:

- CI for commit `30cd810`: successful.
- Local automated suite on the Phase 2 implementation: 29 tests passed with 112 assertions.
- Existing tenant `velora-clinic`: RBAC bootstrap succeeded.
- Tenant RBAC state: 5 roles and 12 permissions present.
- Owner membership: `role_key=owner`, `status=active`.
- PlatformAccount authorization check under the `velora-clinic` permission team resolved the `owner` role.
- Permission team context was explicitly reset to `null` after verification.
- `CACHE_STORE=array` was used only for the local bootstrap verification because local Redis is not running; production architecture remains Redis-backed.

For routine local verification from `C:\Herd\veloraplus`:

~~~powershell
vendor/bin/pint --dirty --format agent
php artisan test --compact
git diff --check
git status
~~~

`php artisan optimize:clear` requires the local Redis service while `CACHE_STORE` is configured for Redis.

## Dependency versions

- laravel/fortify 1.39.0
- spatie/laravel-permission 8.3.0

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
