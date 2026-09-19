<?php

namespace App\Domain\Billing;

use DomainException;

final class BillingMoney
{
    public static function fromMinor(int $amountMinor, string $currency): int
    {
        self::assertCurrency($currency);

        if ($amountMinor < 0) {
            throw new DomainException('Money amount cannot be negative.');
        }

        return $amountMinor;
    }

    public static function multiply(int $amountMinor, int $quantity, string $currency): int
    {
        self::assertCurrency($currency);

        if ($amountMinor < 0 || $quantity < 0) {
            throw new DomainException('Money amount and quantity cannot be negative.');
        }

        if ($quantity !== 0 && $amountMinor > intdiv(PHP_INT_MAX, $quantity)) {
            throw new DomainException('Money multiplication exceeds the supported integer range.');
        }

        return $amountMinor * $quantity;
    }

    public static function percentage(int $amountMinor, int $basisPoints, string $currency): int
    {
        self::assertCurrency($currency);

        if ($amountMinor < 0) {
            throw new DomainException('Money amount cannot be negative.');
        }

        if ($basisPoints < 0 || $basisPoints > 10000) {
            throw new DomainException('Percentage basis points must be between 0 and 10000.');
        }

        $whole = intdiv($amountMinor, 10000) * $basisPoints;
        $remainder = $amountMinor % 10000;
        $fraction = intdiv($remainder * $basisPoints, 10000);

        if ($whole > PHP_INT_MAX - $fraction) {
            throw new DomainException('Percentage result exceeds the supported integer range.');
        }

        return $whole + $fraction;
    }

    private static function assertCurrency(string $currency): void
    {
        if (! preg_match('/^[A-Z]{3}$/', strtoupper($currency))) {
            throw new DomainException('Invalid money currency.');
        }
    }
}
