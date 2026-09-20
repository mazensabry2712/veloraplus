<?php

namespace App\View\Composers;

use App\Application\Company\CompanyBrandingManager;
use App\Application\Dashboard\DashboardContextService;
use App\Domain\Tenancy\TenantContext;
use Illuminate\View\View;

final class DashboardLayoutComposer
{
    public function __construct(
        private readonly DashboardContextService $context,
        private readonly TenantContext $tenantContext,
        private readonly CompanyBrandingManager $brandingManager,
    ) {}

    public function compose(View $view): void
    {
        $view->with([
            'tenant' => $this->tenantContext->current(),
            'membership' => $this->context->membership(),
            'branding' => $this->brandingManager->branding(),
        ]);
    }
}
