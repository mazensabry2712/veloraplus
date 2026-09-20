<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Dashboard\UsageDashboardService;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\View\View;
use Illuminate\Support\Facades\Gate;

final class UsageController extends Controller
{
    public function __invoke(UsageDashboardService $service): View
    {
        Gate::authorize('viewAny', CompanySetting::class);

        $overview = $service->overview();

        return view('dashboard.usage', $overview);
    }
}
