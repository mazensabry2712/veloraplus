<?php

namespace App\Http\Middleware;

use App\Application\Tenancy\TenantMembershipAuthorizer;
use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantMembership
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantMembershipAuthorizer $authorizer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();

        if ($account === null) {
            abort(401);
        }

        if ($account->status !== 'active') {
            abort(403);
        }

        $tenant = $this->context->current();

        if (! $this->authorizer->canAccess($account, $tenant)) {
            abort(403);
        }

        setPermissionsTeamId($tenant->getKey());
        $account->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $next($request);
        } finally {
            $account->unsetRelation('roles')->unsetRelation('permissions');
            setPermissionsTeamId(null);
        }
    }
}
