<?php

namespace App\Http\Controllers\Public;

use App\Application\SEO\SeoManager;
use App\Domain\Tenancy\TenantContext;
use App\Models\Service;
use Illuminate\View\View;

final class PublicBookingController
{
    public function __invoke(string $slug, TenantContext $context, SeoManager $seo): View
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
            'serviceUrl' => $seo->tenantUrl($tenant, '/services/'.$service->slug),
            'seo' => $seo->tenantBooking($tenant, $service),
        ]);
    }
}
