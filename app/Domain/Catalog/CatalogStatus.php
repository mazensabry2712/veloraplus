<?php

namespace App\Domain\Catalog;

use DomainException;

final class CatalogStatus
{
    public const MODULES = [
        'draft',
        'active',
        'coming_soon',
        'deprecated',
        'retired',
    ];

    public const FEATURES = self::MODULES;

    public const BUNDLES = self::MODULES;

    public const PRICES = [
        'draft',
        'active',
        'retired',
    ];

    public const BILLING_MODES = [
        'flat',
        'per_unit',
        'per_seat',
        'usage',
    ];

    public const BILLING_CYCLES = [
        'monthly',
        'yearly',
    ];

    public static function assertOneOf(string $value, array $allowed, string $field): void
    {
        if (! in_array($value, $allowed, true)) {
            throw new DomainException("Invalid {$field}: {$value}");
        }
    }

    public static function assertKey(string $key): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9._-]*$/', $key)) {
            throw new DomainException("Invalid catalog key: {$key}");
        }
    }
}
