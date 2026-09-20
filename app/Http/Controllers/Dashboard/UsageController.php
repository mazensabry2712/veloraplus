<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Dashboard\UsageDashboardService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class UsageController extends Controller
{
    public function __invoke(UsageDashboardService $service): View
    {
        $overview = $service->overview();

        return view('dashboard.usage', $overview);
    }
}
