<?php

namespace App\Application\Billing;

use App\Models\BillingAudit;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

final class BillingAuditService
{
    public function record(
        Tenant $tenant,
        string $action,
        Model $auditable,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $actorAccountId = null,
        ?array $metadata = null,
    ): BillingAudit {
        return BillingAudit::query()->create([
            'tenant_id' => $tenant->getKey(),
            'actor_account_id' => $actorAccountId,
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => $metadata,
        ]);
    }
}