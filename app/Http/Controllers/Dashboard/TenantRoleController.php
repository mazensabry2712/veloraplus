<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Authorization\TenantRoleManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreTenantRoleRequest;
use App\Http\Requests\Dashboard\UpdateTenantRoleRequest;
use App\Models\Role;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class TenantRoleController extends Controller
{
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
