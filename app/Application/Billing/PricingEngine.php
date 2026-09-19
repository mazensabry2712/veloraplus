<?php

namespace App\Application\Billing;

use App\Application\Catalog\CatalogPricingResolver;
use App\Domain\Billing\BillingMoney;
use App\Models\Bundle;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use DomainException;

final class PricingEngine
{
    public function __construct(
        private readonly CatalogPricingResolver $prices,
    ) {
    }

    /**
     * @param array<int, array{item: Module|Feature|Bundle, quantity?: int}> $items
     * @return array{currency: string, country_code: ?string, billing_cycle: string, subtotal_minor: int, discount_minor: int, taxable_subtotal_minor: int, tax_minor: int, total_minor: int, lines: array<int, array<string, mixed>>}
     */
    public function quote(
        Tenant $tenant,
        string $billingCycle,
        array $items,
        ?string $currency = null,
        ?string $countryCode = null,
        int $taxBps = 0,
        ?CarbonImmutable $at = null,
    ): array {
        $billingCycle = strtolower(trim($billingCycle));
        if (! in_array($billingCycle, ['monthly', 'yearly'], true)) {
            throw new DomainException('Billing cycle must be monthly or yearly.');
        }

        if ($taxBps < 0 || $taxBps > 10000) {
            throw new DomainException('Tax basis points must be between 0 and 10000.');
        }

        $currency = strtoupper($currency ?? $tenant->default_currency);
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainException('Invalid billing currency.');
        }

        $countryCode = $countryCode !== null
            ? strtoupper($countryCode)
            : ($tenant->country_code !== null ? strtoupper($tenant->country_code) : null);

        if ($countryCode !== null && ! preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new DomainException('Invalid billing country code.');
        }

        if ($items === []) {
            throw new DomainException('At least one billable catalog item is required.');
        }

        $at ??= CarbonImmutable::now();
        $lines = [];
        $seen = [];
        $subtotal = BillingMoney::fromMinor(0, $currency);
        $discount = BillingMoney::fromMinor(0, $currency);
        $tax = BillingMoney::fromMinor(0, $currency);

        foreach ($items as $input) {
            $item = $input['item'] ?? null;
            if (! $item instanceof Module && ! $item instanceof Feature && ! $item instanceof Bundle) {
                throw new DomainException('Each pricing item must be a Module, Feature, or Bundle.');
            }

            $key = $item->getMorphClass().':'.$item->key;
            if (isset($seen[$key])) {
                throw new DomainException("Catalog item {$item->key} appears more than once in the same quote.");
            }
            $seen[$key] = true;

            $quantity = (int) ($input['quantity'] ?? 1);
            if ($quantity < 1) {
                throw new DomainException('Pricing item quantity must be at least 1.');
            }

            $this->assertPurchasable($item);

            $price = $this->prices->current($item, $billingCycle, $currency, $countryCode);
            if ($price === null) {
                throw new DomainException("No active catalog price exists for {$item->getMorphClass()}:{$item->key}.");
            }

            if (strtoupper($price->currency) !== $currency) {
                throw new DomainException('Catalog price currency does not match the billing currency.');
            }

            $unit = BillingMoney::fromMinor((int) $price->amount_minor, $currency);
            $lineSubtotal = BillingMoney::multiply($unit, $quantity);
            $lineDiscount = $item instanceof Bundle
                ? BillingMoney::percentage($lineSubtotal, (int) $item->discount_bps)
                : BillingMoney::fromMinor(0, $currency);
            $taxable = $lineSubtotal->minus($lineDiscount);
            $lineTax = BillingMoney::percentage($taxable, $taxBps);
            $lineTotal = $taxable->plus($lineTax);

            $subtotal = $subtotal->plus($lineSubtotal);
            $discount = $discount->plus($lineDiscount);
            $tax = $tax->plus($lineTax);

            $lines[] = [
                'description' => $item->name,
                'catalog_type' => $item->getMorphClass(),
                'catalog_key' => $item->key,
                'catalog_price_id' => $price->getKey(),
                'quantity' => $quantity,
                'unit_amount_minor' => BillingMoney::minor($unit),
                'discount_minor' => BillingMoney::minor($lineDiscount),
                'tax_minor' => BillingMoney::minor($lineTax),
                'line_total_minor' => BillingMoney::minor($lineTotal),
                'metadata' => [
                    'bundle_discount_bps' => $item instanceof Bundle ? (int) $item->discount_bps : 0,
                    'tax_bps' => $taxBps,
                    'priced_at' => $at->toIso8601String(),
                ],
            ];
        }

        $taxableSubtotal = $subtotal->minus($discount);
        $total = $taxableSubtotal->plus($tax);

        return [
            'currency' => $currency,
            'country_code' => $countryCode,
            'billing_cycle' => $billingCycle,
            'subtotal_minor' => BillingMoney::minor($subtotal),
            'discount_minor' => BillingMoney::minor($discount),
            'taxable_subtotal_minor' => BillingMoney::minor($taxableSubtotal),
            'tax_minor' => BillingMoney::minor($tax),
            'total_minor' => BillingMoney::minor($total),
            'lines' => $lines,
        ];
    }

    private function assertPurchasable(Module|Feature|Bundle $item): void
    {
        if ($item->status !== 'active') {
            throw new DomainException("Catalog item {$item->key} is not active.");
        }

        if ($item instanceof Module && $item->is_core) {
            throw new DomainException('Core modules are not customer-purchasable.');
        }

        if ($item instanceof Feature && ! $item->is_individually_purchasable) {
            throw new DomainException("Feature {$item->key} is not individually purchasable.");
        }
    }
}
