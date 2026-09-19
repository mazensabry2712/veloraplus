<?php

namespace App\Domain\Billing;

use DomainException;

final class BillingMath
{
    public static function percentageBps(int $amountMinor, int $bps): int
    {
        if ($amountMinor < 0 || $bps < 0 || $bps > 10000) {
            throw new DomainException('Money percentage inputs are invalid.');
        }

        $whole = intdiv($amountMinor, 10000);
        $remainder = $amountMinor % 10000;

        return ($whole * $bps) + intdiv($remainder * $bps, 10000);
    }

    public static function multiply(int $unitAmountMinor, int $quantity): int
    {
        if ($unitAmountMinor < 0 || $quantity < 1) {
            throw new DomainException('Money multiplication inputs are invalid.');
        }

        return $unitAmountMinor * $quantity;
    }

    public static function subtract(int $amountMinor, int $discountMinor): int
    {
        if ($discountMinor < 0 || $discountMinor > $amountMinor) {
            throw new DomainException('Discount cannot exceed the amount.');
        }

        return $amountMinor - $discountMinor;
    }
}