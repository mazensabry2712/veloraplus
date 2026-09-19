<?php

namespace App\Application\Billing\Pricing;

use App\Application\Catalog\CatalogPricingResolver;
use App\Domain\Billing\BillingCycle;
use App\Domain\Billing\BillingMath;
use App\Domain\Catalog\CatalogStatus;
use App\Models\Bundle;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Tenant;
use DomainException;

final class PricingEngine
{
    public function __construct(
        private readonly CatalogPricingResolver $catalogPrices,
    ) {
    }

    /**
     * @param array<int, array{item: Module|Feature|Bundle, quantity?: int}> $selections
     */
    public function calculate(
        Tenant $tenant,
        array $selections,
        BillingCycle $billingCycle,
        ?string $currency = null,
        ?string $countryCode = null,
        int $taxBps = 0,
    ): PricingBreakdown {
        $currency = strtoupper($currency ?? $tenant->default_currency);
        $countryCode ??= $tenant->country_code;
        $countryCode = $countryCode !== null ? strtoupper($countryCode) : null;

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainException("Invalid billing currency: {$currency}");
        }

        if ($taxBps < 0 || $taxBps > 10000) {
            throw new DomainException('Tax basis points must be between 0 and 10000.');
        }

        $lines = [];
        $seen = [];
        $subtotal = 0;
        $discount = 0;

        foreach ($selections as $selection) {
            $item = $selection['item'] ?? null;
            $quantity = (int) ($selection['quantity'] ?? 1);

            if (! $item instanceof Module && ! $item instanceof Feature && ! $item instanceof Bundle) {
                throw new DomainException('Each pricing selection must contain a Module, Feature, or Bundle.');
            }

            if ($quantity < 1) {
                throw new DomainException('Pricing quantity must be at least 1.');
            }

            $key = $item->getMorphClass().':'.$item->getKey();

            if (isset($seen[$key])) {
                throw new DomainException('The same catalog item cannot be selected more than once.');
            }

            $seen[$key] = true;

            $line = match (true) {
                $item instanceof Bundle => $this->bundleLine($item, $quantity, $billingCycle, $currency, $countryCode),
                $item instanceof Feature => $this->featureLine($item, $quantity, $billingCycle, $currency, $countryCode),
                default => $this->moduleLine($item, $quantity, $billingCycle, $currency, $countryCode),
            };

            $taxMinor = BillingMath::percentageBps($line['line_total_minor'], $taxBps);

            $lines[] = new PricingLine(
                $line['catalog_type'],
                $line['catalog_key'],
                $line['description'],
                $quantity,
                $line['unit_amount_minor'],
                $line['discount_minor'],
                $taxMinor,
                $line['line_total_minor'],
                $line['catalog_price_id'],
            );

            $subtotal += BillingMath::multiply($line['unit_amount_minor'], $quantity);
            $discount += $line['discount_minor'];
        }

        $taxableSubtotal = BillingMath::subtract($subtotal, $discount);
        $tax = array_sum(array_map(
            static fn (PricingLine $line): int => $line->taxMinor,
            $lines,
        ));

        return new PricingBreakdown(
            $subtotal,
            $discount,
            $taxableSubtotal,
            $tax,
            $taxableSubtotal + $tax,
            $currency,
            $lines,
        );
    }

    private function moduleLine(
        Module $module,
        int $quantity,
        BillingCycle $cycle,
        string $currency,
        ?string $countryCode,
    ): array {
        $this->assertActive($module->status, 'module');

        if ($module->is_core) {
            throw new DomainException('Core modules are not purchasable.');
        }

        $price = $this->catalogPrices->current($module, $cycle->value, $currency, $countryCode);

        throw_if($price === null, DomainException::class, "No {$cycle->value} price exists for module {$module->key}.");

        return [
            'catalog_type' => 'module',
            'catalog_key' => $module->key,
            'description' => $module->name,
            'unit_amount_minor' => $price->amount_minor,
            'discount_minor' => 0,
            'line_total_minor' => BillingMath::multiply($price->amount_minor, $quantity),
            'catalog_price_id' => $price->getKey(),
        ];
    }

    private function featureLine(
        Feature $feature,
        int $quantity,
        BillingCycle $cycle,
        string $currency,
        ?string $countryCode,
    ): array {
        $this->assertActive($feature->status, 'feature');

        if (! $feature->is_individually_purchasable) {
            throw new DomainException("Feature {$feature->key} cannot be purchased individually.");
        }

        if ($feature->billing_mode === 'usage') {
            throw new DomainException("Usage feature {$feature->key} is not billed in Phase 5.");
        }

        if ($feature->billing_mode === 'flat' && $quantity !== 1) {
            throw new DomainException("Flat feature {$feature->key} must have quantity 1.");
        }

        $price = $this->catalogPrices->current($feature, $cycle->value, $currency, $countryCode);

        throw_if($price === null, DomainException::class, "No {$cycle->value} price exists for feature {$feature->key}.");

        return [
            'catalog_type' => 'feature',
            'catalog_key' => $feature->key,
            'description' => $feature->name,
            'unit_amount_minor' => $price->amount_minor,
            'discount_minor' => 0,
            'line_total_minor' => BillingMath::multiply($price->amount_minor, $quantity),
            'catalog_price_id' => $price->getKey(),
        ];
    }

    private function bundleLine(
        Bundle $bundle,
        int $quantity,
        BillingCycle $cycle,
        string $currency,
        ?string $countryCode,
    ): array {
        $this->assertActive($bundle->status, 'bundle');

        $bundlePrice = $this->catalogPrices->current($bundle, $cycle->value, $currency, $countryCode);
        $componentTotal = $this->bundleComponentUnitTotal($bundle, $cycle, $currency, $countryCode);

        if ($bundlePrice !== null) {
            $baseUnit = $componentTotal;
            $discountUnit = max(0, $componentTotal - $bundlePrice->amount_minor);
            $priceId = $bundlePrice->getKey();
        } else {
            $baseUnit = $componentTotal;
            $discountUnit = BillingMath::percentageBps($baseUnit, $bundle->discount_bps);
            $priceId = null;
        }

        $gross = BillingMath::multiply($baseUnit, $quantity);
        $lineDiscount = BillingMath::multiply($discountUnit, $quantity);

        return [
            'catalog_type' => 'bundle',
            'catalog_key' => $bundle->key,
            'description' => $bundle->name,
            'unit_amount_minor' => $baseUnit,
            'discount_minor' => $lineDiscount,
            'line_total_minor' => BillingMath::subtract($gross, $lineDiscount),
            'catalog_price_id' => $priceId,
        ];
    }

    private function bundleComponentUnitTotal(
        Bundle $bundle,
        BillingCycle $cycle,
        string $currency,
        ?string $countryCode,
    ): int {
        $bundle->loadMissing(['modules', 'features']);
        $moduleKeys = $bundle->modules->pluck('key')->all();
        $total = 0;

        foreach ($bundle->modules as $module) {
            $this->assertActive($module->status, 'module');

            if ($module->is_core) {
                throw new DomainException('Core modules cannot be included in a purchasable bundle.');
            }

            $price = $this->catalogPrices->current($module, $cycle->value, $currency, $countryCode);

            throw_if($price === null, DomainException::class, "No {$cycle->value} price exists for module {$module->key}.");

            $total += $price->amount_minor;
        }

        foreach ($bundle->features as $feature) {
            $feature->loadMissing('module');
            $this->assertActive($feature->status, 'feature');

            if (in_array($feature->module?->key, $moduleKeys, true)) {
                continue;
            }

            $price = $this->catalogPrices->current($feature, $cycle->value, $currency, $countryCode);

            throw_if($price === null, DomainException::class, "No {$cycle->value} price exists for feature {$feature->key}.");

            $total += $price->amount_minor;
        }

        if ($total === 0) {
            throw new DomainException("Bundle {$bundle->key} contains no billable catalog items.");
        }

        return $total;
    }

    private function assertActive(string $status, string $type): void
    {
        if ($status !== 'active') {
            throw new DomainException("The {$type} must be active before it can be billed.");
        }

        CatalogStatus::assertOneOf($status, CatalogStatus::MODULES, $type.' status');
    }
}