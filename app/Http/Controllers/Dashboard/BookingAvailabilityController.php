<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Booking\StaffAvailabilityManager;
use App\Application\Entitlements\EntitlementService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreStaffBreakRequest;
use App\Http\Requests\Dashboard\StoreStaffTimeOffRequest;
use App\Http\Requests\Dashboard\StoreStaffWorkingHourRequest;
use App\Http\Requests\Dashboard\UpdateStaffBreakRequest;
use App\Http\Requests\Dashboard\UpdateStaffTimeOffRequest;
use App\Http\Requests\Dashboard\UpdateStaffWorkingHourRequest;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffBreak;
use App\Models\StaffTimeOff;
use App\Models\StaffWorkingHour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

final class BookingAvailabilityController extends Controller
{
    public function index(TenantContext $tenantContext, EntitlementService $entitlements): View
    {
        Gate::authorize('booking.availability.view');

        $tenant = $tenantContext->current();
        $canManage = auth()->user()->can('booking.availability.manage');
        $servicesEntitled = $entitlements->canUse($tenant, 'booking.services');

        $services = $servicesEntitled
            ? Service::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('dashboard.booking.availability', [
            'tenant' => $tenant,
            'canManage' => $canManage,
            'servicesEntitled' => $servicesEntitled,
            'services' => $services,
            'staffMembers' => Staff::query()
                ->with([
                    'location:id,name',
                    'services:id,name',
                    'workingHours' => fn ($query) => $query->orderBy('day_of_week')->orderBy('starts_at')->with('breaks'),
                    'timeOffs' => fn ($query) => $query->orderBy('starts_at')->limit(10),
                ])
                ->where('status', 'active')
                ->orderBy('name')
                ->paginate(10),
        ]);
    }

    public function assignService(
        Staff $staff,
        Service $service,
        StaffAvailabilityManager $manager,
    ): RedirectResponse {
        Gate::authorize('manageAvailability', $staff);

        try {
            $manager->assignService($staff, $service);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['availability' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Service assigned to staff successfully.');
    }

    public function unassignService(
        Staff $staff,
        Service $service,
        StaffAvailabilityManager $manager,
    ): RedirectResponse {
        Gate::authorize('manageAvailability', $staff);

        $manager->unassignService($staff, $service);

        return to_route('dashboard')->with('status', 'Service unassigned from staff successfully.');
    }

    public function storeWorkingHour(
        StoreStaffWorkingHourRequest $request,
        Staff $staff,
        StaffAvailabilityManager $manager,
    ): RedirectResponse {
        Gate::authorize('manageAvailability', $staff);

        try {
            $data = $request->validated();

            $manager->saveWorkingHour(
                $staff,
                (int) $data['day_of_week'],
                (string) $data['starts_at'],
                (string) $data['ends_at'],
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['working_hours' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Staff working hour saved successfully.');
    }

    public function updateWorkingHour(
        UpdateStaffWorkingHourRequest $request,
        Staff $staff,
        StaffWorkingHour $workingHour,
        StaffAvailabilityManager $manager,
    ): RedirectResponse {
        Gate::authorize('manageAvailability', $staff);

        try {
            $data = $request->validated();

            $manager->saveWorkingHour(
                $staff,
                (int) $data['day_of_week'],
                (string) $data['starts_at'],
                (string) $data['ends_at'],
                $workingHour,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['working_hours' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Staff working hour updated successfully.');
    }

    public function destroyWorkingHour(
        Staff $staff,
        StaffWorkingHour $workingHour,
        StaffAvailabilityManager $manager,
    ): RedirectResponse {
        Gate::authorize('manageAvailability', $staff);

        if ((string) $workingHour->staff_id !== (string) $staff->getKey()) {
            abort(404);
        }

        $manager->deleteWorkingHour($workingHour);

        return to_route('dashboard')->with('status', 'Staff working hour deleted successfully.');
    }

    public function storeBreak(
        StoreStaffBreakRequest $request,
        Staff $staff,
        StaffWorkingHour $workingHour,
        StaffAvailabilityManager $manager,
    ): RedirectResponse {
        Gate::authorize('manageAvailability', $staff);

        $this->assertWorkingHourBelongsToStaff($workingHour, $staff);

        try {
            $data = $request->validated();

            $manager->saveBreak(
                $workingHour,
                (string) $data['starts_at'],
                (string) $data['ends_at'],
                $data['label'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['breaks' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Staff break saved successfully.');
    }

    public function updateBreak(
        UpdateStaffBreakRequest $request,
        Staff $staff,
        StaffWorkingHour $workingHour,
        StaffBreak $break,
        StaffAvailabilityManager $manager,
    ): RedirectResponse {
        Gate::authorize('manageAvailability', $staff);

        $this->assertWorkingHourBelongsToStaff($workingHour, $staff);

        if ((string) $break->staff_working_hour_id !== (string) $workingHour->getKey()) {
            abort(404);
        }

        try {
            $data = $request->validated();

            $manager->saveBreak(
                $workingHour,
                (string) $data['starts_at'],
                (string) $data['ends_at'],
                $data['label'] ?? null,
                $break,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['breaks' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Staff break updated successfully.');
    }

    public function destroyBreak(
        Staff $staff,
        StaffWorkingHour $workingHour,
        StaffBreak $break,
        StaffAvailabilityManager $manager,
    ): RedirectResponse {
        Gate::authorize('manageAvailability', $staff);

        $this->assertWorkingHourBelongsToStaff($workingHour, $staff);

        if ((string) $break->staff_working_hour_id !== (string) $workingHour->getKey()) {
            abort(404);
        }

        $manager->deleteBreak($break);

        return to_route('dashboard')->with('status', 'Staff break deleted successfully.');
    }

    public function storeTimeOff(
        StoreStaffTimeOffRequest $request,
        Staff $staff,
        StaffAvailabilityManager $manager,
    ): RedirectResponse {
        Gate::authorize('manageAvailability', $staff);

        try {
            $data = $request->validated();

            $manager->saveTimeOff(
                $staff,
                (string) $data['starts_at'],
                (string) $data['ends_at'],
                $data['reason'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['time_off' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Staff time off saved successfully.');
    }

    public function updateTimeOff(
        UpdateStaffTimeOffRequest $request,
        Staff $staff,
        StaffTimeOff $timeOff,
        StaffAvailabilityManager $manager,
    ): RedirectResponse {
        Gate::authorize('manageAvailability', $staff);

        if ((string) $timeOff->staff_id !== (string) $staff->getKey()) {
            abort(404);
        }

        try {
            $data = $request->validated();

            $manager->saveTimeOff(
                $staff,
                (string) $data['starts_at'],
                (string) $data['ends_at'],
                $data['reason'] ?? null,
                $timeOff,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['time_off' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Staff time off updated successfully.');
    }

    public function destroyTimeOff(
        Staff $staff,
        StaffTimeOff $timeOff,
        StaffAvailabilityManager $manager,
    ): RedirectResponse {
        Gate::authorize('manageAvailability', $staff);

        if ((string) $timeOff->staff_id !== (string) $staff->getKey()) {
            abort(404);
        }

        $manager->deleteTimeOff($timeOff);

        return to_route('dashboard')->with('status', 'Staff time off deleted successfully.');
    }

    private function assertWorkingHourBelongsToStaff(
        StaffWorkingHour $workingHour,
        Staff $staff,
    ): void {
        if ((string) $workingHour->staff_id !== (string) $staff->getKey()) {
            abort(404);
        }
    }
}
