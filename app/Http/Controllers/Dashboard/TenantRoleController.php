<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Authorization\TenantRoleManager;
use App\Application\Authorization\TenantRbacBootstrapper;
use App\Domain\Tenancy\TenantContext;
use App\Models\Permission;
use App\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreTenantRoleRequest;
use App\Http\Requests\Dashboard\UpdateTenantRoleRequest;
use App\Models\Role;
use DomainException;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class TenantRoleController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        Gate::authorize('members.view');

        $tenant = $tenantContext->current();

        return view('dashboard.company.roles', [
            'roles' => Role::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('guard_name', TenantRbacBootstrapper::GUARD)
                ->with('permissions')
                ->orderBy('name')
                ->get(),
            'permissions' => Permission::query()
                ->where('guard_name', TenantRbacBootstrapper::GUARD)
                ->whereIn('name', TenantRbacBootstrapper::PERMISSIONS)
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }


    public function store(
        StoreTenantRoleRequest $request,
        TenantRoleManager $manager,
    ): RedirectResponse {
        Gate::authorize('create', Role::class);

        try {
            $manager->create($request->validated());
        } catch (DomainException $exception) {
            return back()->withErrors(['name' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')->with('status', 'Custom role created successfully.');
    }

    public function update(
        UpdateTenantRoleRequest $request,
        Role $role,
        TenantRoleManager $manager,
    ): RedirectResponse {
        Gate::authorize('update', $role);

        try {
            $manager->update($role, $request->validated());
        } catch (DomainException $exception) {
            return back()->withErrors(['permissions' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')->with('status', 'Custom role updated successfully.');
    }

    public function destroy(
        Role $role,
        TenantRoleManager $manager,
    ): RedirectResponse {
        Gate::authorize('delete', $role);

        try {
            $manager->delete($role);
        } catch (DomainException $exception) {
            return back()->withErrors(['role' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Custom role deleted successfully.');
    }
}
