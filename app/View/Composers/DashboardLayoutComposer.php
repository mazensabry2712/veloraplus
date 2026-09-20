<?php

namespace App\View\Composers;

use App\Application\Dashboard\DashboardContextService;
use Illuminate\View\View;

final class DashboardLayoutComposer
{
    public function __construct(
        private readonly DashboardContextService $context,
    ) {}

    public function compose(View $view): void
    {
        $view->with('membership', $this->context->membership());
    }
}
