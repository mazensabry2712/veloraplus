<?php

namespace App\Application\Billing;

use App\Models\BillingAuditEvent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

final class BillingAuditLogger
{
    public function record(
        Tenant $tenant,
        string $action,
        ?Model $subject = null,
        array $metadata = [],
    ): BillingAuditEvent {
        $actorAccountId = null;

        if (auth()->check()) {
            $actorAccountId = (string) auth()->id();
        }

        return BillingAuditEvent::query()->create([
            'tenant_id' => $tenant->getKey(),
            'actor_account_id' => $actorAccountId,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata ?: null,
        ]);
    }
}
