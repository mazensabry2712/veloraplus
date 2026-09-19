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
        $subtotal = 0;
        $discount = 0;
        $tax = 0;

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
            $lineSubtotal = BillingMoney::multiply($unit, $quantity, $currency);
            $lineDiscount = $item instanceof Bundle
                ? BillingMoney::percentage($lineSubtotal, (int) $item->discount_bps, $currency)
                : 0;
            $taxable = $lineSubtotal - $lineDiscount;
            $lineTax = BillingMoney::percentage($taxable, $taxBps, $currency);

            if ($lineDiscount > $lineSubtotal) {
                throw new DomainException('Line discount cannot exceed line subtotal.');
            }

            if ($taxable > PHP_INT_MAX - $lineTax) {
                throw new DomainException('Line total exceeds the supported integer range.');
            }

            $lineTotal = $taxable + $lineTax;

            foreach ([
                'subtotal' => [$subtotal, $lineSubtotal],
                'discount' => [$discount, $lineDiscount],
                'tax' => [$tax, $lineTax],
            ] as $name => [$current, $increment]) {
                if ($current > PHP_INT_MAX - $increment) {
                    throw new DomainException("{$name} total exceeds the supported integer range.");
                }
            }

            $subtotal += $lineSubtotal;
            $discount += $lineDiscount;
            $tax += $lineTax;

            $lines[] = [
                'description' => $item->name,
                'catalog_type' => $item->getMorphClass(),
                'catalog_key' => $item->key,
                'catalog_price_id' => $price->getKey(),
                'quantity' => $quantity,
                'unit_amount_minor' => $unit,
                'discount_minor' => $lineDiscount,
                'tax_minor' => $lineTax,
                'line_total_minor' => $lineTotal,
                'metadata' => [
                    'bundle_discount_bps' => $item instanceof Bundle ? (int) $item->discount_bps : 0,
                    'tax_bps' => $taxBps,
                    'priced_at' => $at->toIso8601String(),
                ],
            ];
        }

        $taxableSubtotal = $subtotal - $discount;
        if ($taxableSubtotal < 0) {
            throw new DomainException('Discount cannot exceed subtotal.');
        }

        if ($taxableSubtotal > PHP_INT_MAX - $tax) {
            throw new DomainException('Total exceeds the supported integer range.');
        }

        return [
            'currency' => $currency,
            'country_code' => $countryCode,
            'billing_cycle' => $billingCycle,
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'taxable_subtotal_minor' => $taxableSubtotal,
            'tax_minor' => $tax,
            'total_minor' => $taxableSubtotal + $tax,
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
