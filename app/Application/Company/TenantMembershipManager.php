<?php

namespace App\Application\Company;

use App\Domain\Tenancy\TenantContext;
use App\Models\PlatformAccount;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use DomainException;
use Illuminate\Support\Facades\DB;

final class TenantMembershipManager
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function addByEmail(string $email, string $roleKey = 'staff'): TenantMembership
    {
        $tenant = $this->tenantContext->current();

        return DB::connection('central')->transaction(function () use ($tenant, $email, $roleKey): TenantMembership {
            $account = PlatformAccount::query()
                ->where('email', strtolower(trim($email)))
                ->first();

            if ($account === null) {
                throw new DomainException('Platform account was not found.');
            }

            if ($account->status !== 'active') {
                throw new DomainException('Suspended platform accounts cannot be added to a tenant.');
            }

            $role = $this->tenantRole($tenant, $roleKey);

            $membership = TenantMembership::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('account_id', $account->getKey())
                ->first();

            if ($membership !== null) {
                throw new DomainException('This account is already a member of the current tenant.');
            }

            $membership = TenantMembership::query()->create([
                'tenant_id' => $tenant->getKey(),
                'account_id' => $account->getKey(),
                'role_key' => $role->name,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $this->syncAccountRoles($account, $role);

            return $membership->load('account');
        });
    }

    /**
     * @param  array{role_key?: string, status?: string}  $attributes
     */
    public function update(TenantMembership $membership, array $attributes): TenantMembership
    {
        $tenant = $this->tenantContext->current();

        $this->assertMembershipBelongsToTenant($membership, $tenant);

        return DB::connection('central')->transaction(function () use ($membership, $attributes, $tenant): TenantMembership {
            $account = $membership->account()->firstOrFail();
            $roleKey = array_key_exists('role_key', $attributes)
                ? trim((string) $attributes['role_key'])
                : (string) $membership->role_key;
            $status = array_key_exists('status', $attributes)
                ? strtolower(trim((string) $attributes['status']))
                : (string) $membership->status;

            $role = $this->tenantRole($tenant, $roleKey);

            if ($membership->role_key === 'owner'
                && ($roleKey !== 'owner' || $status !== 'active')
                && $this->activeOwnerCount($tenant) === 1) {
                throw new DomainException('The tenant must retain at least one active owner.');
            }

            if ($status === 'active' && $account->status !== 'active') {
                throw new DomainException('Suspended platform accounts cannot have an active tenant membership.');
            }

            $membership->update([
                'role_key' => $role->name,
                'status' => $status,
                'joined_at' => $membership->joined_at ?? now(),
            ]);

            $this->syncAccountRoles($account, $status === 'active' ? $role : null);

            return $membership->refresh()->load('account');
        });
    }

    public function deactivate(TenantMembership $membership): TenantMembership
    {
        return $this->update($membership, [
            'status' => 'inactive',
        ]);
    }

    private function tenantRole(Tenant $tenant, string $roleKey): Role
    {
        if ($roleKey === '') {
            throw new DomainException('Membership role is required.');
        }

        $role = Role::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('guard_name', 'web')
            ->where('name', $roleKey)
            ->first();

        if ($role === null) {
            throw new DomainException('The selected tenant role does not exist.');
        }

        return $role;
    }

    private function syncAccountRoles(PlatformAccount $account, ?Role $role): void
    {
        $previousTeamId = getPermissionsTeamId();
        $tenantId = $this->tenantContext->current()->getKey();

        setPermissionsTeamId($tenantId);

        try {
            $account->unsetRelation('roles')->unsetRelation('permissions');
            $account->syncRoles($role === null ? [] : [$role]);
        } finally {
            $account->unsetRelation('roles')->unsetRelation('permissions');
            setPermissionsTeamId($previousTeamId);
        }
    }

    private function assertMembershipBelongsToTenant(TenantMembership $membership, Tenant $tenant): void
    {
        if ($membership->tenant_id !== $tenant->getKey()) {
            throw new DomainException('Membership does not belong to the current tenant.');
        }
    }

    private function activeOwnerCount(Tenant $tenant): int
    {
        return TenantMembership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->where('role_key', 'owner')
            ->count();
    }
}
