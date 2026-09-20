<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Booking\AppointmentManager;
use App\Application\Entitlements\EntitlementService;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CancelAppointmentRequest;
use App\Http\Requests\Dashboard\RescheduleAppointmentRequest;
use App\Http\Requests\Dashboard\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class AppointmentController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenantContext,
        EntitlementService $entitlements,
    ): View {
        Gate::authorize('viewAny', Appointment::class);

        $tenant = $tenantContext->current();
        $timezone = $tenant->timezone ?: config('app.timezone', 'UTC');

        $status = AppointmentStatus::tryFrom($request->string('status')->toString());
        $search = trim($request->string('q')->toString());
        $date = trim($request->string('date')->toString());

        $appointments = Appointment::query()
            ->select([
                'id',
                'customer_id',
                'staff_id',
                'location_id',
                'starts_at',
                'ends_at',
                'status',
                'payment_status',
                'cancellation_reason',
                'notes',
            ])
            ->with([
                'customer:id,name,phone,email',
                'staff:id,name,location_id',
                'location:id,name,timezone',
                'items:id,appointment_id,service_name,quantity,unit_price_minor,line_total_minor,currency',
            ])
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->whereHas('customer', fn ($customer) => $customer
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('staff', fn ($staff) => $staff->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('items', fn ($item) => $item->where('service_name', 'like', "%{$search}%"));
                });
            })
            ->when($date !== '', function ($query) use ($date, $timezone): void {
                try {
                    $localStart = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);

                    if ($localStart->format('Y-m-d') !== $date) {
                        return;
                    }

                    $utcStart = $localStart->startOfDay()->utc();
                    $utcEnd = $localStart->endOfDay()->utc();

                    $query->whereBetween('starts_at', [$utcStart, $utcEnd]);
                } catch (\Throwable) {
                    // Invalid filter input is ignored; business request validation remains unchanged.
                }
            })
            ->orderBy('starts_at')
            ->paginate(20)
            ->withQueryString();

        $canManage = auth()->user()->can('booking.appointments.manage');

        $customers = Customer::query()
            ->select(['id', 'name', 'phone'])
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(100)
            ->get();

        $staffMembers = Staff::query()
            ->select(['id', 'name', 'location_id'])
            ->with('location:id,name')
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(100)
            ->get();

        $services = Service::query()
            ->select(['id', 'name', 'duration_minutes', 'price_minor', 'currency'])
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(100)
            ->get();

        return view('dashboard.booking.appointments', [
            'tenant' => $tenant,
            'timezone' => $timezone,
            'appointments' => $appointments,
            'canManage' => $canManage,
            'customers' => $customers,
            'staffMembers' => $staffMembers,
            'services' => $services,
            'hasAppointmentEntitlement' => $entitlements->canUse($tenant, 'booking.appointments'),
        ]);
    }

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
