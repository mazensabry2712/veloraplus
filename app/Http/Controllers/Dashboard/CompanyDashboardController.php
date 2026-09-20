<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Dashboard\CompanyDashboardSummaryService;
use App\Application\Dashboard\DashboardContextService;
use App\Application\Entitlements\EntitlementService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class CompanyDashboardController extends Controller
{
    public function __invoke(
        TenantContext $tenantContext,
        DashboardContextService $dashboardContext,
        CompanyDashboardSummaryService $summaryService,
        EntitlementService $entitlementService,
    ): View {
        $tenant = $tenantContext->current();
        $account = auth()->user();

        $quickLinks = [
            [
                'label' => 'Services',
                'description' => 'Manage booking services, pricing, and lifecycle.',
                'route' => 'dashboard.booking.services.index',
                'permission' => 'booking.services.view',
                'entitlement' => 'booking.services',
                'icon' => 'briefcase',
            ],
            [
                'label' => 'Availability',
                'description' => 'Review staff hours, breaks, and time off.',
                'route' => 'dashboard.booking.availability.index',
                'permission' => 'booking.availability.view',
                'entitlement' => 'booking.availability',
                'icon' => 'clock',
            ],
            [
                'label' => 'Appointments',
                'description' => 'Search and manage the booking schedule.',
                'route' => 'dashboard.booking.appointments.index',
                'permission' => 'booking.appointments.view',
                'entitlement' => 'booking.appointments',
                'icon' => 'calendar',
            ],
            [
                'label' => 'Queue',
                'description' => 'Operate the daily customer waiting queue.',
                'route' => 'dashboard.booking.queues.index',
                'permission' => 'booking.queues.view',
                'entitlement' => 'booking.queues',
                'icon' => 'queue',
            ],
        ];

        $quickLinks = array_values(array_filter(
            $quickLinks,
            fn (array $link): bool => $account->can($link['permission'])
                && $entitlementService->canUse($tenant, $link['entitlement']),
        ));

        return view('dashboard.index', [
            'tenant' => $tenant,
            'membership' => $dashboardContext->membership(),
            'summary' => $summaryService->summarize($tenant),
            'quickLinks' => $quickLinks,
        ]);
    }
}
