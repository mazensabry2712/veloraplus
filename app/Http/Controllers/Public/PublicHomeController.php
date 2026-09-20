<?php

namespace App\Http\Controllers\Public;

use App\Application\Company\CompanyBrandingManager;
use App\Application\Company\TenantSettingsManager;
use App\Application\SEO\SeoManager;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class PublicHomeController extends Controller
{
    public function __invoke(
        TenantContext $context,
        SeoManager $seo,
        TenantSettingsManager $settings,
        CompanyBrandingManager $branding,
    ): View {
        if ($context->check()) {
            $tenant = $context->current();

            return view('public.tenant-home', [
                'tenant' => $tenant,
                'seo' => $seo->tenantHome($tenant),
                'branding' => $branding->branding(),
                'social' => $settings->social(),
            ]);
        }

        abort(404);
    }
}
