<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Company\TenantMembershipManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreTenantMembershipRequest;
use App\Http\Requests\Dashboard\UpdateTenantMembershipRequest;
use App\Models\TenantMembership;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class TenantMembershipController extends Controller
{
    public function store(
        StoreTenantMembershipRequest $request,
        TenantMembershipManager $manager,
    ): RedirectResponse {
        Gate::authorize('create', TenantMembership::class);

        try {
            $manager->addByEmail(
                $request->validated('email'),
                $request->validated('role_key'),
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['email' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')->with('status', 'Tenant member added successfully.');
    }

    public function update(
        UpdateTenantMembershipRequest $request,
        TenantMembership $membership,
        TenantMembershipManager $manager,
    ): RedirectResponse {
        Gate::authorize('update', $membership);

        try {
            $manager->update($membership, $request->validated());
        } catch (DomainException $exception) {
            return back()->withErrors(['role_key' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')->with('status', 'Tenant membership updated successfully.');
    }

    public function destroy(
        TenantMembership $membership,
        TenantMembershipManager $manager,
    ): RedirectResponse {
        Gate::authorize('delete', $membership);

        try {
            $manager->deactivate($membership);
        } catch (DomainException $exception) {
            return back()->withErrors(['membership' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Tenant membership deactivated successfully.');
    }
}
