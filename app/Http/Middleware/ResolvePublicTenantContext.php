<?php

namespace App\Http\Middleware;

use App\Application\SEO\SeoManager;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantResolver;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolvePublicTenantContext
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantDatabaseManager $databaseManager,
        private readonly TenantContext $context,
        private readonly SeoManager $seo,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->seo->isPlatformHost($request->getHost())) {
            return $next($request);
        }

        $tenant = $this->resolver->resolve($request->getHost());

        if ($tenant === null || $tenant->database_status !== 'ready') {
            abort(404);
        }

        $this->context->set($tenant);
        $this->databaseManager->connect($tenant);

        try {
            return $next($request);
        } finally {
            $this->databaseManager->disconnect();
            $this->context->clear();
        }
    }
}
