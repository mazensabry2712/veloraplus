<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Company\StaffManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreStaffRequest;
use App\Http\Requests\Dashboard\UpdateStaffRequest;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class StaffController extends Controller
{
    public function store(StoreStaffRequest $request, StaffManager $manager): RedirectResponse
    {
        Gate::authorize('create', Staff::class);

        $manager->create($request->validated());

        return to_route('dashboard')->with('status', 'Staff member created successfully.');
    }

    public function update(
        UpdateStaffRequest $request,
        Staff $staff,
        StaffManager $manager,
    ): RedirectResponse {
        Gate::authorize('update', $staff);

        $manager->update($staff, $request->validated());

        return to_route('dashboard')->with('status', 'Staff member updated successfully.');
    }

    public function destroy(Staff $staff, StaffManager $manager): RedirectResponse
    {
        Gate::authorize('delete', $staff);

        $manager->archive($staff);

        return to_route('dashboard')->with('status', 'Staff member archived successfully.');
    }
}
