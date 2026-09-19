<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Models\PlatformAccount;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

afterEach(function (): void {
    setPermissionsTeamId(null);
});

test('tenant payment policy follows the booking payment permissions', function (): void {
    $managerAccount = PlatformAccount::factory()->create();
    $viewerAccount = PlatformAccount::factory()->create();
    $tenant = Tenant::factory()->create();

    $bootstrapper = app(TenantRbacBootstrapper::class);
    $bootstrapper->bootstrapForTenant($tenant);

    $tenant->memberships()->create([
        'account_id' => $managerAccount->getKey(),
        'role_key' => 'manager',
        'status' => 'active',
    ]);

    $tenant->memberships()->create([
        'account_id' => $viewerAccount->getKey(),
        'role_key' => 'viewer',
        'status' => 'active',
    ]);

    $bootstrapper->bootstrapForTenant($tenant);

    setPermissionsTeamId($tenant->getKey());

    $managerAccount->unsetRelation('roles')->unsetRelation('permissions');
    $viewerAccount->unsetRelation('roles')->unsetRelation('permissions');

    expect(Gate::forUser($managerAccount)->allows('create', App\Models\TenantPayment::class))->toBeTrue()
        ->and(Gate::forUser($viewerAccount)->allows('create', App\Models\TenantPayment::class))->toBeFalse()
        ->and(Gate::forUser($viewerAccount)->allows('viewAny', App\Models\TenantPayment::class))->toBeTrue();
});
