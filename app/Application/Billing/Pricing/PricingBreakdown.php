<?php

namespace App\Application\Billing\Pricing;

final readonly class PricingBreakdown
{
    /**
     * @param array<int, PricingLine> $lines
     */
    public function __construct(
        public int $subtotalMinor,
        public int $discountMinor,
        public int $taxableSubtotalMinor,
        public int $taxMinor,
        public int $totalMinor,
        public string $currency,
        public array $lines,
    ) {
    }

    public function toArray(): array
    {
        return [
            'subtotal_minor' => $this->subtotalMinor,
            'discount_minor' => $this->discountMinor,
            'taxable_subtotal_minor' => $this->taxableSubtotalMinor,
            'tax_minor' => $this->taxMinor,
            'total_minor' => $this->totalMinor,
            'currency' => $this->currency,
            'lines' => array_map(
                static fn (PricingLine $line): array => $line->toArray(),
                $this->lines,
            ),
        ];
    }
}