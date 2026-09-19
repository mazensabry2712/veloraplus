<?php

namespace App\Http\Controllers\Public;

use App\Application\SEO\SeoManager;
use App\Domain\Tenancy\TenantContext;
use App\Models\Service;
use App\Models\ServiceSlugRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class PublicServiceController
{
    public function index(TenantContext $context, SeoManager $seo): View
    {
        if (! $context->check()) {
            abort(404);
        }

        $tenant = $context->current();

        $services = Service::query()
            ->where('status', 'active')
            ->where('online_bookable', true)
            ->orderBy('name')
            ->get();

        return view('public.services.index', [
            'tenant' => $tenant,
            'services' => $services,
            'serviceUrls' => $services->mapWithKeys(
                fn (Service $service): array => [
                    $service->getKey() => $seo->tenantUrl($tenant, '/services/'.$service->slug),
                ],
            ),
            'seo' => $seo->tenantServices($tenant),
        ]);
    }

    public function show(string $slug, TenantContext $context, SeoManager $seo): View|RedirectResponse
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
            $redirect = ServiceSlugRedirect::query()
                ->where('old_slug', $slug)
                ->first();

            if ($redirect !== null) {
                $current = Service::query()
                    ->whereKey($redirect->service_id)
                    ->where('status', 'active')
                    ->where('online_bookable', true)
                    ->first();

                if ($current !== null && $current->slug === $redirect->new_slug) {
                    return redirect()->to($seo->tenantUrl($tenant, '/services/'.$current->slug), 301);
                }
            }

            abort(404);
        }

        return view('public.services.show', [
            'tenant' => $tenant,
            'service' => $service,
            'homeUrl' => $seo->tenantUrl($tenant),
            'servicesUrl' => $seo->tenantUrl($tenant, '/services'),
            'bookingUrl' => $seo->tenantUrl($tenant, '/book/'.$service->slug),
            'seo' => $seo->tenantService($tenant, $service),
        ]);
    }
}
