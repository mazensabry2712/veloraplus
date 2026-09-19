<?php

namespace App\Application\Billing;

use App\Domain\Billing\CreditEntryType;
use App\Models\PlatformCredit;
use App\Models\Tenant;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreditService
{
    public function grant(
        Tenant $tenant,
        int $amountMinor,
        string $currency,
        string $sourceType,
        ?string $sourceId = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): PlatformCredit {
        if ($amountMinor < 1 || ! preg_match('/^[A-Z]{3}$/', strtoupper($currency))) {
            throw new DomainException('Credit inputs are invalid.');
        }

        return PlatformCredit::query()->create([
            'tenant_id' => $tenant->getKey(),
            'entry_type' => CreditEntryType::Credit,
            'amount_minor' => $amountMinor,
            'currency' => strtoupper($currency),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'expires_at' => null,
            'metadata' => null,
        ]);
    }

    public function balance(Tenant $tenant, string $currency): int
    {
        $currency = strtoupper($currency);

        $credits = PlatformCredit::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('currency', $currency)
            ->where('entry_type', CreditEntryType::Credit->value)
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->sum('amount_minor');

        $debits = PlatformCredit::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('currency', $currency)
            ->where('entry_type', CreditEntryType::Debit->value)
            ->sum('amount_minor');

        return max(0, (int) $credits - (int) $debits);
    }
}