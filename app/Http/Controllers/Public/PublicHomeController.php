<?php

namespace App\Http\Controllers\Public;

use App\Application\SEO\SeoManager;
use App\Domain\Tenancy\TenantContext;
use Illuminate\View\View;

final class PublicHomeController
{
    public function __invoke(TenantContext $context, SeoManager $seo): View
    {
        if ($context->check()) {
            $tenant = $context->current();

            return view('public.tenant-home', [
                'tenant' => $tenant,
                'seo' => $seo->tenantHome($tenant),
            ]);
        }

        return view('welcome', [
            'seo' => $seo->platformHome(),
        ]);
    }
}
