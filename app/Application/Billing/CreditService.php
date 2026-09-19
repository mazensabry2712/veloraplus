<?php

namespace App\Application\Billing;

use App\Domain\Billing\CreditStatus;
use App\Models\PlatformCredit;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use DomainException;

final class CreditService
{
    public function __construct(
        private readonly BillingAuditLogger $audit,
    ) {
    }

    public function issue(
        Tenant $tenant,
        int $amountMinor,
        string $currency,
        ?string $source = null,
        ?CarbonImmutable $expiresAt = null,
        array $metadata = [],
    ): PlatformCredit {
        if ($amountMinor < 1) {
            throw new DomainException('Credit amount must be positive.');
        }

        $currency = strtoupper($currency);
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainException('Invalid credit currency.');
        }

        if ($expiresAt !== null && $expiresAt <= CarbonImmutable::now()) {
            throw new DomainException('Credit expiration must be in the future.');
        }

        $credit = PlatformCredit::query()->create([
            'tenant_id' => $tenant->getKey(),
            'amount_minor' => $amountMinor,
            'remaining_minor' => $amountMinor,
            'currency' => $currency,
            'status' => CreditStatus::Active,
            'source' => $source,
            'expires_at' => $expiresAt,
            'metadata' => $metadata ?: null,
        ]);

        $this->audit->record($tenant, 'credit.issued', $credit, [
            'amount_minor' => $amountMinor,
            'currency' => $currency,
        ]);

        return $credit;
    }
}
