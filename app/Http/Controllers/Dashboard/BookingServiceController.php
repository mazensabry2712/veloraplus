<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Booking\ServiceManager;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreBookingServiceRequest;
use App\Http\Requests\Dashboard\UpdateBookingServiceRequest;
use App\Models\Service;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class BookingServiceController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        Gate::authorize('viewAny', Service::class);

        return view('dashboard.booking.services', [
            'tenant' => $tenantContext->current(),
            'services' => Service::query()
                ->select([
                    'id',
                    'name',
                    'slug',
                    'description',
                    'duration_minutes',
                    'buffer_before_minutes',
                    'buffer_after_minutes',
                    'price_minor',
                    'currency',
                    'deposit_amount_minor',
                    'status',
                    'online_bookable',
                    'capacity',
                ])
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function store(
        StoreBookingServiceRequest $request,
        ServiceManager $manager,
    ): RedirectResponse {
        Gate::authorize('create', Service::class);

        try {
            $manager->create($request->validated());
        } catch (DomainException $exception) {
            return back()->withErrors(['service' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')->with('status', 'Booking service created successfully.');
    }

    public function update(
        UpdateBookingServiceRequest $request,
        Service $service,
        ServiceManager $manager,
    ): RedirectResponse {
        Gate::authorize('update', $service);

        try {
            $manager->update($service, $request->validated());
        } catch (DomainException $exception) {
            return back()->withErrors(['service' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')->with('status', 'Booking service updated successfully.');
    }

    public function destroy(
        Service $service,
        ServiceManager $manager,
    ): RedirectResponse {
        Gate::authorize('delete', $service);

        try {
            $manager->archive($service);
        } catch (DomainException $exception) {
            return back()->withErrors(['service' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')->with('status', 'Booking service archived successfully.');
    }
}
