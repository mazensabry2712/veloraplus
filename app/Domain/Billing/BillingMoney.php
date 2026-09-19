<?php

namespace App\Domain\Billing;

use Brick\Money\Money;
use Brick\Math\RoundingMode;
use DomainException;

final class BillingMoney
{
    public static function fromMinor(int $amountMinor, string $currency): Money
    {
        if ($amountMinor < 0) {
            throw new DomainException('Money amount cannot be negative.');
        }

        return Money::ofMinor($amountMinor, strtoupper($currency));
    }

    public static function multiply(Money $money, int $quantity): Money
    {
        if ($quantity < 0) {
            throw new DomainException('Money quantity cannot be negative.');
        }

        return $money->multipliedBy($quantity);
    }

    public static function percentage(Money $money, int $basisPoints): Money
    {
        if ($basisPoints < 0 || $basisPoints > 10000) {
            throw new DomainException('Percentage basis points must be between 0 and 10000.');
        }

        return $money
            ->multipliedBy($basisPoints)
            ->dividedBy(10000, RoundingMode::Down);
    }

    public static function minor(Money $money): int
    {
        return $money->getMinorAmount()->toInt();
    }
}
