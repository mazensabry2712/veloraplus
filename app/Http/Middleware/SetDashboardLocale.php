<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetDashboardLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('dashboard_locale', config('app.locale', 'en'));

        if (in_array($locale, ['en', 'ar'], true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
