<?php

namespace App\Http\Middleware;

use App\Application\Entitlements\EntitlementService;
use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantEntitlement
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly EntitlementService $entitlements,
    ) {
    }

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $tenant = $this->context->get();

        if ($tenant === null || ! $this->entitlements->canUse($tenant, $capability)) {
            abort(403);
        }

        return $next($request);
    }
}
