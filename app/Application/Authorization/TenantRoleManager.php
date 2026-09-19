<?php

namespace App\Application\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\TenantMembership;
use App\Domain\Tenancy\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class TenantRoleManager
{
    /** @var list<string> */
    private const SYSTEM_ROLES = [
        'owner',
        'admin',
        'manager',
        'staff',
        'viewer',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Role
    {
        $tenant = $this->tenantContext->current();
        $name = $this->normalizeName($attributes['name'] ?? null);

        $this->assertCustomRoleName($name);
        $permissions = $this->permissions($attributes['permissions'] ?? []);

        return DB::connection('central')->transaction(function () use ($tenant, $name, $permissions): Role {
            $role = Role::query()->create([
                'name' => $name,
                'guard_name' => TenantRbacBootstrapper::GUARD,
                'tenant_id' => $tenant->getKey(),
            ]);

            $this->syncPermissions($role, $permissions);

            return $role->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Role $role, array $attributes): Role
    {
        $tenant = $this->tenantContext->current();

        $this->assertRoleBelongsToTenant($role, $tenant);

        if ($this->isSystemRole($role->name)) {
            throw new DomainException('System roles cannot be modified from the tenant dashboard.');
        }

        $permissions = $this->permissions($attributes['permissions'] ?? []);

        return DB::connection('central')->transaction(function () use ($role, $permissions): Role {
            $this->syncPermissions($role, $permissions);

            return $role->refresh();
        });
    }

    public function delete(Role $role): void
    {
        $tenant = $this->tenantContext->current();

        $this->assertRoleBelongsToTenant($role, $tenant);

        if ($this->isSystemRole($role->name)) {
            throw new DomainException('System roles cannot be deleted from the tenant dashboard.');
        }

        if (TenantMembership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('role_key', $role->name)
            ->exists()) {
            throw new DomainException('A role assigned to a tenant membership cannot be deleted.');
        }

        DB::connection('central')->transaction(function () use ($role): void {
            $role->delete();
        });
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeName(mixed $value): string
    {
        $name = trim((string) $value);

        if ($name === '') {
            throw new DomainException('Role name is required.');
        }

        if (mb_strlen($name) > 100) {
            throw new DomainException('Role name exceeds the allowed length.');
        }

        return $name;
    }

    /**
     * @param  mixed  $value
     * @return list<Permission>
     */
    private function permissions(mixed $value): array
    {
        if (! is_array($value)) {
            throw new DomainException('Role permissions must be an array.');
        }

        $names = array_values(array_unique(array_map(
            fn (mixed $permission): string => trim((string) $permission),
            $value,
        )));

        if ($names === []) {
            return [];
        }

        $permissions = Permission::query()
            ->where('guard_name', TenantRbacBootstrapper::GUARD)
            ->whereIn('name', $names)
            ->get()
            ->keyBy('name');

        if ($permissions->count() !== count($names)) {
            $missing = collect($names)
                ->reject(fn (string $name): bool => $permissions->has($name))
                ->values()
                ->implode(', ');

            throw new DomainException('Unknown permissions: '.$missing);
        }

        return $permissions->values()->all();
    }

    /**
     * @param  list<Permission>  $permissions
     */
    private function syncPermissions(Role $role, array $permissions): void
    {
        $previousTeamId = getPermissionsTeamId();

        setPermissionsTeamId($role->tenant_id);

        try {
            $role->syncPermissions($permissions);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    private function assertCustomRoleName(string $name): void
    {
        if ($this->isSystemRole($name)) {
            throw new DomainException('System role names are reserved.');
        }

        if (Role::query()
            ->where('tenant_id', $this->tenantContext->current()->getKey())
            ->where('guard_name', TenantRbacBootstrapper::GUARD)
            ->where('name', $name)
            ->exists()) {
            throw new DomainException('A role with this name already exists in the current tenant.');
        }
    }

    private function assertRoleBelongsToTenant(Role $role, \App\Models\Tenant $tenant): void
    {
        if ($role->tenant_id !== $tenant->getKey()) {
            throw new DomainException('Role does not belong to the current tenant.');
        }
    }

    private function isSystemRole(string $name): bool
    {
        return in_array($name, self::SYSTEM_ROLES, true);
    }
}
