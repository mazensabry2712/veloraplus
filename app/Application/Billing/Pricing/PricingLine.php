<?php

namespace App\Application\Billing\Pricing;

final readonly class PricingLine
{
    public function __construct(
        public string $catalogType,
        public string $catalogKey,
        public string $description,
        public int $quantity,
        public int $unitAmountMinor,
        public int $discountMinor,
        public int $taxMinor,
        public int $lineTotalMinor,
        public ?string $catalogPriceId = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'catalog_type' => $this->catalogType,
            'catalog_key' => $this->catalogKey,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_amount_minor' => $this->unitAmountMinor,
            'discount_minor' => $this->discountMinor,
            'tax_minor' => $this->taxMinor,
            'line_total_minor' => $this->lineTotalMinor,
            'catalog_price_id' => $this->catalogPriceId,
        ];
    }
}