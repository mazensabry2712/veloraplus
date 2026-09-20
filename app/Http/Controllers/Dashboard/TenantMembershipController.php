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
use Illuminate\View\View;

final class TenantMembershipController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        Gate::authorize('members.view');

        $tenant = $tenantContext->current();

        return view('dashboard.company.users', [
            'memberships' => $tenant->memberships()
                ->with('account')
                ->orderByDesc('joined_at')
                ->paginate(20),
            'roles' => Role::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }


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
