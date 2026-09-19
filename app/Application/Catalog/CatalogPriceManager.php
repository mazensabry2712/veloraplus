<?php

namespace App\Application\Catalog;

use App\Domain\Catalog\CatalogStatus;
use App\Models\Bundle;
use App\Models\CatalogPrice;
use App\Models\Feature;
use App\Models\Module;
use Carbon\CarbonImmutable;
use DomainException;

final class CatalogPriceManager
{
    public function add(Module|Feature|Bundle $priceable, array $attributes): CatalogPrice
    {
        $billingCycle = strtolower((string) ($attributes['billing_cycle'] ?? ''));
        $currency = strtoupper((string) ($attributes['currency'] ?? ''));
        $countryCode = array_key_exists('country_code', $attributes) && $attributes['country_code'] !== null
            ? strtoupper((string) $attributes['country_code'])
            : null;
        $status = strtolower((string) ($attributes['status'] ?? 'active'));
        $amountMinor = $attributes['amount_minor'] ?? null;
        $effectiveFrom = isset($attributes['effective_from'])
            ? CarbonImmutable::parse($attributes['effective_from'])
            : now()->toImmutable();
        $effectiveTo = isset($attributes['effective_to']) && $attributes['effective_to'] !== null
            ? CarbonImmutable::parse($attributes['effective_to'])
            : null;

        CatalogStatus::assertOneOf($billingCycle, CatalogStatus::BILLING_CYCLES, 'billing cycle');
        CatalogStatus::assertOneOf($status, CatalogStatus::PRICES, 'price status');

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainException("Invalid catalog currency: {$currency}");
        }

        if ($countryCode !== null && ! preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new DomainException("Invalid catalog country code: {$countryCode}");
        }

        if (! is_int($amountMinor) && ! ctype_digit((string) $amountMinor)) {
            throw new DomainException('Catalog price amount_minor must be a non-negative integer.');
        }

        $amountMinor = (int) $amountMinor;

        if ($amountMinor < 0) {
            throw new DomainException('Catalog price amount_minor must be a non-negative integer.');
        }

        if ($effectiveTo !== null && $effectiveTo <= $effectiveFrom) {
            throw new DomainException('Catalog price effective_to must be after effective_from.');
        }

        if ($priceable instanceof Module && $priceable->is_core) {
            throw new DomainException('Core modules are not customer-purchasable and cannot have catalog prices.');
        }

        if ($status === 'active') {
            $this->assertNoOverlappingActivePrice(
                $priceable,
                $billingCycle,
                $currency,
                $countryCode,
                $effectiveFrom,
                $effectiveTo,
            );
        }

        return CatalogPrice::query()->create([
            'priceable_type' => $priceable->getMorphClass(),
            'priceable_id' => $priceable->getKey(),
            'billing_cycle' => $billingCycle,
            'currency' => $currency,
            'country_code' => $countryCode,
            'amount_minor' => $amountMinor,
            'status' => $status,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    private function assertNoOverlappingActivePrice(
        Module|Feature|Bundle $priceable,
        string $billingCycle,
        string $currency,
        ?string $countryCode,
        CarbonImmutable $effectiveFrom,
        ?CarbonImmutable $effectiveTo,
    ): void {
        $existing = CatalogPrice::query()
            ->where('priceable_type', $priceable->getMorphClass())
            ->where('priceable_id', $priceable->getKey())
            ->where('billing_cycle', $billingCycle)
            ->where('currency', $currency)
            ->where('status', 'active')
            ->when(
                $countryCode === null,
                fn ($query) => $query->whereNull('country_code'),
                fn ($query) => $query->where('country_code', $countryCode)
            )
            ->get(['effective_from', 'effective_to']);

        foreach ($existing as $price) {
            $existingFrom = CarbonImmutable::parse($price->effective_from);
            $existingTo = $price->effective_to !== null
                ? CarbonImmutable::parse($price->effective_to)
                : null;

            $startsBeforeExistingEnds = $existingTo === null || $effectiveFrom < $existingTo;
            $existingStartsBeforeNewEnds = $effectiveTo === null || $existingFrom < $effectiveTo;

            if ($startsBeforeExistingEnds && $existingStartsBeforeNewEnds) {
                throw new DomainException('An active catalog price already overlaps this pricing window.');
            }
        }
    }
}
