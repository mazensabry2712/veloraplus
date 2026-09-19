<?php

namespace App\Http\Controllers\Public;

use App\Application\SEO\SeoManager;
use App\Domain\Tenancy\TenantContext;
use App\Models\Service;
use App\Http\Requests\PublicBookingRequest;
use App\Application\Booking\PublicBookingManager;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class PublicBookingController
{
    public function __invoke(
        string $slug,
        TenantContext $context,
        SeoManager $seo,
        PublicBookingManager $bookings,
    ): View
    {
        if (! $context->check()) {
            abort(404);
        }

        $tenant = $context->current();

        $service = Service::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->where('online_bookable', true)
            ->first();

        if ($service === null) {
            abort(404);
        }

        return view('public.booking.show', [
            'tenant' => $tenant,
            'service' => $service,
            'staff' => $bookings->availableStaff($service),
            'serviceUrl' => $seo->tenantUrl($tenant, '/services/'.$service->slug),
            'seo' => $seo->tenantBooking($tenant, $service),
        ]);
    }
}

    
    public function store(
        PublicBookingRequest $request,
        string $slug,
        TenantContext $context,
        PublicBookingManager $bookings,
        SeoManager $seo,
    ): View|RedirectResponse {
        if (! $context->check()) {
            abort(404);
        }

        $service = $request->service();

        if ($service === null) {
            abort(404);
        }

        try {
            $result = $bookings->book($service, $request->validated());
        } catch (DomainException $exception) {
            throw $exception;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'appointment_id' => $result['appointment']->getKey(),
                'payment_status' => $result['appointment']->payment_status?->value,
                'checkout_url' => $result['checkout_url'],
            ]);
        }

        if ($result['checkout_url'] !== null) {
            return redirect()->away($result['checkout_url']);
        }

        return view('public.booking.success', [
            'tenant' => $context->current(),
            'service' => $service,
            'appointment' => $result['appointment'],
            'serviceUrl' => $seo->tenantUrl($context->current(), '/services/'.$service->slug),
        ]);
    }
