<?php

use App\Http\Middleware\EnsureTenantEntitlement;
use App\Http\Middleware\InitializeTenantContext;
use App\Http\Middleware\NoIndexRobots;
use App\Http\Middleware\ResolvePublicTenantContext;
use App\Http\Middleware\SetDashboardLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => InitializeTenantContext::class,
            'tenant.member' => \App\Http\Middleware\EnsureTenantMembership::class,
            'entitled' => EnsureTenantEntitlement::class,
            'noindex' => NoIndexRobots::class,
            'public.tenant' => ResolvePublicTenantContext::class,
            'dashboard.locale' => SetDashboardLocale::class,
        ]);

        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: InitializeTenantContext::class,
        );

        $middleware->web(append: [
            SetDashboardLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
