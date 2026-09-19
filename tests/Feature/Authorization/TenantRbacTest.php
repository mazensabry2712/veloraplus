<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Application\Tenancy\CreateTenant;
use App\Application\Tenancy\TenantProvisionerContract;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\EnsureTenantMembership;
use App\Models\PlatformAccount;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

afterEach(function () {
    setPermissionsTeamId(null);
});

test('tenant rbac bootstrap is idempotent and creates default permissions and roles', function () {
    $tenant = Tenant::factory()->create();

    $bootstrapper = app(TenantRbacBootstrapper::class);
    $bootstrapper->bootstrapForTenant($tenant);
    $bootstrapper->bootstrapForTenant($tenant);

    expect(config('permission.models.permission'))->toBe(App\Models\Permission::class)
        ->and(config('permission.models.role'))->toBe(App\Models\Role::class)
        ->and(App\Models\Permission::query()->where('guard_name', 'web')->count())->toBe(20)
        ->and(App\Models\Role::query()->where('tenant_id', $tenant->getKey())->count())->toBe(5)
        ->and(App\Models\Role::query()->where('tenant_id', $tenant->getKey())->pluck('name')->all())
        ->toEqualCanonicalizing(['owner', 'admin', 'manager', 'staff', 'viewer']);
});

test('booking availability permissions are assigned by role', function () {
    $tenant = Tenant::factory()->create();
    $bootstrapper = app(TenantRbacBootstrapper::class);
    $bootstrapper->bootstrapForTenant($tenant);

    setPermissionsTeamId($tenant->getKey());

    $roles = App\Models\Role::query()
        ->where('tenant_id', $tenant->getKey())
        ->get()
        ->keyBy('name');

    expect($roles['owner']->hasPermissionTo('booking.availability.manage'))->toBeTrue()
        ->and($roles['manager']->hasPermissionTo('booking.availability.manage'))->toBeTrue()
        ->and($roles['staff']->hasPermissionTo('booking.availability.view'))->toBeTrue()
        ->and($roles['staff']->hasPermissionTo('booking.availability.manage'))->toBeFalse()
        ->and($roles['viewer']->hasPermissionTo('booking.availability.view'))->toBeTrue()
        ->and($roles['viewer']->hasPermissionTo('booking.availability.manage'))->toBeFalse();
});


test('queue permissions are assigned by role', function () {
    $tenant = Tenant::factory()->create();
    $bootstrapper = app(TenantRbacBootstrapper::class);
    $bootstrapper->bootstrapForTenant($tenant);

    setPermissionsTeamId($tenant->getKey());

    $roles = App\Models\Role::query()
        ->where('tenant_id', $tenant->getKey())
        ->get()
        ->keyBy('name');

    expect($roles['owner']->hasPermissionTo('booking.queues.manage'))->toBeTrue()
        ->and($roles['manager']->hasPermissionTo('booking.queues.manage'))->toBeTrue()
        ->and($roles['staff']->hasPermissionTo('booking.queues.view'))->toBeTrue()
        ->and($roles['staff']->hasPermissionTo('booking.queues.manage'))->toBeFalse()
        ->and($roles['viewer']->hasPermissionTo('booking.queues.view'))->toBeTrue()
        ->and($roles['viewer']->hasPermissionTo('booking.queues.manage'))->toBeFalse();
});

test('booking payment permissions are assigned by role', function () {
    $tenant = Tenant::factory()->create();
    $bootstrapper = app(TenantRbacBootstrapper::class);
    $bootstrapper->bootstrapForTenant($tenant);

    setPermissionsTeamId($tenant->getKey());

    $roles = App\Models\Role::query()
        ->where('tenant_id', $tenant->getKey())
        ->get()
        ->keyBy('name');

    expect($roles['owner']->hasPermissionTo('booking.payments.manage'))->toBeTrue()
        ->and($roles['manager']->hasPermissionTo('booking.payments.manage'))->toBeTrue()
        ->and($roles['staff']->hasPermissionTo('booking.payments.view'))->toBeTrue()
        ->and($roles['staff']->hasPermissionTo('booking.payments.manage'))->toBeFalse()
        ->and($roles['viewer']->hasPermissionTo('booking.payments.view'))->toBeTrue()
        ->and($roles['viewer']->hasPermissionTo('booking.payments.manage'))->toBeFalse();
});

test('booking appointment permissions are assigned by role', function () {
    $tenant = Tenant::factory()->create();
    $bootstrapper = app(TenantRbacBootstrapper::class);
    $bootstrapper->bootstrapForTenant($tenant);

    setPermissionsTeamId($tenant->getKey());

    $roles = App\Models\Role::query()
        ->where('tenant_id', $tenant->getKey())
        ->get()
        ->keyBy('name');

    expect($roles['owner']->hasPermissionTo('booking.appointments.manage'))->toBeTrue()
        ->and($roles['manager']->hasPermissionTo('booking.appointments.manage'))->toBeTrue()
        ->and($roles['staff']->hasPermissionTo('booking.appointments.view'))->toBeTrue()
        ->and($roles['staff']->hasPermissionTo('booking.appointments.manage'))->toBeFalse()
        ->and($roles['viewer']->hasPermissionTo('booking.appointments.view'))->toBeTrue()
        ->and($roles['viewer']->hasPermissionTo('booking.appointments.manage'))->toBeFalse();
});

test('tenant rbac bootstrap synchronizes a changed membership role', function () {
    $account = PlatformAccount::factory()->create();
    $tenant = Tenant::factory()->create();

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $account->getKey(),
        'role_key' => 'manager',
        'status' => 'active',
    ]);

    $bootstrapper = app(TenantRbacBootstrapper::class);
    $bootstrapper->bootstrapForTenant($tenant);

    setPermissionsTeamId($tenant->getKey());
    $account->unsetRelation('roles')->unsetRelation('permissions');

    expect($account->hasRole('manager'))->toBeTrue();

    TenantMembership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('account_id', $account->getKey())
        ->update(['role_key' => 'viewer']);

    $bootstrapper->bootstrapForTenant($tenant);

    setPermissionsTeamId($tenant->getKey());
    $account->unsetRelation('roles')->unsetRelation('permissions');

    expect($account->hasRole('viewer'))
        ->toBeTrue()
        ->and($account->hasRole('manager'))
        ->toBeFalse()
        ->and($account->hasPermissionTo('customers.manage'))
        ->toBeFalse();
});

test('one platform account can have different roles in different tenants', function () {
    $account = PlatformAccount::factory()->create();
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    TenantMembership::create([
        'tenant_id' => $tenantA->getKey(),
        'account_id' => $account->getKey(),
        'role_key' => 'manager',
        'status' => 'active',
    ]);

    TenantMembership::create([
        'tenant_id' => $tenantB->getKey(),
        'account_id' => $account->getKey(),
        'role_key' => 'viewer',
        'status' => 'active',
    ]);

    $bootstrapper = app(TenantRbacBootstrapper::class);
    $bootstrapper->bootstrapForTenant($tenantA);
    $bootstrapper->bootstrapForTenant($tenantB);

    setPermissionsTeamId($tenantA->getKey());
    $account->unsetRelation('roles')->unsetRelation('permissions');

    expect($account->hasRole('manager'))
        ->toBeTrue()
        ->and($account->hasPermissionTo('customers.manage'))
        ->toBeTrue()
        ->and($account->hasPermissionTo('members.manage'))
        ->toBeFalse();

    setPermissionsTeamId($tenantB->getKey());
    $account->unsetRelation('roles')->unsetRelation('permissions');

    expect($account->hasRole('viewer'))
        ->toBeTrue()
        ->and($account->hasRole('manager'))
        ->toBeFalse()
        ->and($account->hasPermissionTo('customers.manage'))
        ->toBeFalse();
});

test('membership middleware establishes tenant team and clears it after the request', function () {
    $account = PlatformAccount::factory()->create();
    $tenant = Tenant::factory()->create();

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $account->getKey(),
        'role_key' => 'owner',
        'status' => 'active',
    ]);

    $context = app(TenantContext::class);
    $context->set($tenant);

    $request = Request::create('/tenant/test', 'GET');
    $request->setUserResolver(fn () => $account);

    $response = app(EnsureTenantMembership::class)->handle(
        $request,
        function () use ($tenant): Response {
            expect(getPermissionsTeamId())->toBe($tenant->getKey());

            return new Response('ok', 200);
        },
    );

    expect($response->getStatusCode())->toBe(200)
        ->and(getPermissionsTeamId())->toBeNull();
});

test('membership middleware blocks non-members and suspended accounts', function () {
    $tenant = Tenant::factory()->create();
    $outsider = PlatformAccount::factory()->create();
    $suspended = PlatformAccount::factory()->suspended()->create();

    $context = app(TenantContext::class);
    $context->set($tenant);

    $outsiderRequest = Request::create('/tenant/test', 'GET');
    $outsiderRequest->setUserResolver(fn () => $outsider);

    expect(fn () => app(EnsureTenantMembership::class)->handle(
        $outsiderRequest,
        fn () => new Response('unexpected', 200),
    ))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

    $suspendedRequest = Request::create('/tenant/test', 'GET');
    $suspendedRequest->setUserResolver(fn () => $suspended);

    expect(fn () => app(EnsureTenantMembership::class)->handle(
        $suspendedRequest,
        fn () => new Response('unexpected', 200),
    ))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

    expect(getPermissionsTeamId())->toBeNull();
});

test('new tenant creation bootstraps the owner role', function () {
    $owner = PlatformAccount::factory()->create();

    $provisioner = Mockery::mock(TenantProvisionerContract::class);
    $provisioner->shouldReceive('provision')
        ->once()
        ->andReturnUsing(fn (Tenant $tenant): Tenant => $tenant);

    app()->instance(TenantProvisionerContract::class, $provisioner);

    $tenant = app(CreateTenant::class)->execute(
        owner: $owner,
        name: 'Bootstrap Tenant',
        slug: 'bootstrap-tenant',
    );

    setPermissionsTeamId($tenant->getKey());
    $owner->unsetRelation('roles')->unsetRelation('permissions');

    expect($owner->hasRole('owner'))->toBeTrue();
});
