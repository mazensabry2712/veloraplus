<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantResolver;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InitializeTenantContext
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantContext $context,
        private readonly TenantDatabaseManager $databaseManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolve($request->getHost());

        if ($tenant === null || $tenant->database_status !== 'ready') {
            abort(404);
        }

        $this->context->set($tenant);

        try {
            $this->databaseManager->connect($tenant);

            return $next($request);
        } finally {
            $this->databaseManager->disconnect();
            $this->context->clear();
        }
    }
}