<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Booking\StaffAvailabilityManager;
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
use InvalidArgumentException;

final class BookingAvailabilityController extends Controller
{
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
            $manager->saveWorkingHour($staff, ...$request->validated());
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
            $manager->saveWorkingHour(
                $staff,
                $workingHour->day_of_week,
                $workingHour->starts_at,
                $workingHour->ends_at,
                $workingHour,
            );

            $manager->saveWorkingHour($staff, ...$request->validated(), $workingHour);
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
            $manager->saveBreak($workingHour, ...array_values($request->validated()));
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
            $manager->saveBreak($workingHour, ...array_values($request->validated()), $break);
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
            $manager->saveTimeOff($staff, ...array_values($request->validated()));
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
            $manager->saveTimeOff($staff, ...array_values($request->validated()), $timeOff);
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
