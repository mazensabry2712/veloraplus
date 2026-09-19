<?php

namespace App\Application\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;

final class TenantRbacBootstrapper
{
    public const GUARD = 'web';

    /** @var list<string> */
    public const PERMISSIONS = [
        'company.view',
        'company.update',
        'members.view',
        'members.manage',
        'staff.view',
        'staff.manage',
        'customers.view',
        'customers.manage',
        'locations.view',
        'locations.manage',
        'settings.view',
        'settings.manage',
        'booking.services.view',
        'booking.services.manage',
        'booking.availability.view',
        'booking.availability.manage',
        'booking.appointments.view',
        'booking.appointments.manage',
        'booking.payments.view',
        'booking.payments.manage',
        'booking.queues.view',
        'booking.queues.manage',
    ];

    /**
     * @return array<string, list<string>>
     */
    public function rolePermissionMap(): array
    {
        return [
            'owner' => self::PERMISSIONS,
            'admin' => self::PERMISSIONS,
            'manager' => [
                'company.view',
                'members.view',
                'staff.view',
                'staff.manage',
                'customers.view',
                'customers.manage',
                'locations.view',
                'locations.manage',
                'settings.view',
                'booking.services.view',
                'booking.services.manage',
                'booking.availability.view',
                'booking.availability.manage',
                'booking.appointments.view',
                'booking.appointments.manage',
                'booking.payments.view',
                'booking.payments.manage',
                'booking.queues.view',
                'booking.queues.manage',
            ],
            'staff' => [
                'company.view',
                'staff.view',
                'customers.view',
                'customers.manage',
                'locations.view',
                'booking.services.view',
                'booking.availability.view',
                'booking.appointments.view',
                'booking.payments.view',
                'booking.queues.view',
                'booking.queues.manage',
            ],
            'viewer' => [
                'company.view',
                'members.view',
                'staff.view',
                'customers.view',
                'locations.view',
                'settings.view',
                'booking.services.view',
                'booking.availability.view',
                'booking.appointments.view',
                'booking.payments.view',
                'booking.queues.view',
            ],
        ];
    }

    public function bootstrapForTenant(Tenant $tenant): void
    {
        $previousTeamId = getPermissionsTeamId();

        setPermissionsTeamId($tenant->getKey());

        try {
            $permissions = $this->ensurePermissions();
            $roles = $this->ensureRoles($permissions);

            $tenant->memberships()
                ->where('status', 'active')
                ->with('account')
                ->get()
                ->each(function (TenantMembership $membership) use ($roles): void {
                    $roleName = (string) $membership->role_key;

                    if (! isset($roles[$roleName]) || $membership->account === null) {
                        return;
                    }

                    $account = $membership->account;
                    $account->unsetRelation('roles')->unsetRelation('permissions');
                    $account->syncRoles([$roles[$roleName]]);
                });
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * @return array<string, Permission>
     */
    private function ensurePermissions(): array
    {
        $permissions = [];

        foreach (self::PERMISSIONS as $name) {
            $permissions[$name] = Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => self::GUARD,
            ]);
        }

        return $permissions;
    }

    /**
     * @param  array<string, Permission>  $permissions
     * @return array<string, Role>
     */
    private function ensureRoles(array $permissions): array
    {
        $roles = [];

        foreach ($this->rolePermissionMap() as $roleName => $permissionNames) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => self::GUARD,
                'tenant_id' => getPermissionsTeamId(),
            ]);

            $role->syncPermissions(
                collect($permissionNames)
                    ->map(fn (string $name): Permission => $permissions[$name])
                    ->values()
                    ->all(),
            );

            $roles[$roleName] = $role;
        }

        return $roles;
    }
}
