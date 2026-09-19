<?php

namespace App\Application\Catalog;

use App\Models\Bundle;
use App\Models\CatalogPrice;
use App\Models\Feature;
use App\Models\Module;

final class CatalogPricingResolver
{
    public function current(
        Module|Feature|Bundle $priceable,
        string $billingCycle,
        string $currency,
        ?string $countryCode = null,
    ): ?CatalogPrice {
        $billingCycle = strtolower($billingCycle);
        $currency = strtoupper($currency);
        $countryCode = $countryCode !== null ? strtoupper($countryCode) : null;
        $now = now();

        $query = CatalogPrice::query()
            ->where('priceable_type', $priceable->getMorphClass())
            ->where('priceable_id', $priceable->getKey())
            ->where('billing_cycle', $billingCycle)
            ->where('currency', $currency)
            ->where('status', 'active')
            ->where('effective_from', '<=', $now)
            ->where(fn ($query) => $query
                ->whereNull('effective_to')
                ->orWhere('effective_to', '>', $now));

        if ($countryCode === null) {
            $query->whereNull('country_code');
        } else {
            $query->where(fn ($query) => $query
                ->where('country_code', $countryCode)
                ->orWhereNull('country_code'))
                ->orderByRaw(
                    'CASE WHEN country_code = ? THEN 0 ELSE 1 END',
                    [$countryCode]
                );
        }

        return $query
            ->orderByDesc('effective_from')
            ->first();
    }
}
