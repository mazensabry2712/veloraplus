<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Booking\AppointmentManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CancelAppointmentRequest;
use App\Http\Requests\Dashboard\RescheduleAppointmentRequest;
use App\Http\Requests\Dashboard\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class AppointmentController extends Controller
{
    public function store(
        StoreAppointmentRequest $request,
        AppointmentManager $manager,
    ): RedirectResponse {
        Gate::authorize('create', Appointment::class);

        try {
            $data = $request->validated();

            $customer = Customer::query()->findOrFail($data['customer_id']);
            $staff = Staff::query()->findOrFail($data['staff_id']);
            $service = Service::query()->findOrFail($data['service_id']);

            $manager->create(
                customer: $customer,
                staff: $staff,
                service: $service,
                startsAt: $data['starts_at'],
                attributes: [
                    'location_id' => $data['location_id'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'idempotency_key' => $data['idempotency_key'] ?? null,
                ],
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')->with('status', 'Appointment created successfully.');
    }

    public function reschedule(
        RescheduleAppointmentRequest $request,
        Appointment $appointment,
        AppointmentManager $manager,
    ): RedirectResponse {
        Gate::authorize('update', $appointment);

        try {
            $manager->reschedule(
                $appointment,
                $request->validated('starts_at'),
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')->with('status', 'Appointment rescheduled successfully.');
    }

    public function confirm(Appointment $appointment, AppointmentManager $manager): RedirectResponse
    {
        Gate::authorize('manage', $appointment);

        try {
            $manager->confirm($appointment);
        } catch (DomainException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Appointment confirmed successfully.');
    }

    public function complete(Appointment $appointment, AppointmentManager $manager): RedirectResponse
    {
        Gate::authorize('manage', $appointment);

        try {
            $manager->complete($appointment);
        } catch (DomainException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Appointment completed successfully.');
    }

    public function cancel(
        CancelAppointmentRequest $request,
        Appointment $appointment,
        AppointmentManager $manager,
    ): RedirectResponse {
        Gate::authorize('manage', $appointment);

        try {
            $manager->cancel($appointment, $request->validated('reason'));
        } catch (DomainException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Appointment cancelled successfully.');
    }

    public function noShow(Appointment $appointment, AppointmentManager $manager): RedirectResponse
    {
        Gate::authorize('manage', $appointment);

        try {
            $manager->markNoShow($appointment);
        } catch (DomainException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Appointment marked as no-show.');
    }
}
