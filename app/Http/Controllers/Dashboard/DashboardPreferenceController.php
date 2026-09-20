<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DashboardPreferenceController extends Controller
{
    public function locale(Request $request): RedirectResponse
    {
        $locale = $request->string('locale')->toString();

        abort_unless(in_array($locale, ['en', 'ar'], true), 422);

        $request->session()->put('dashboard_locale', $locale);

        return back();
    }
}
