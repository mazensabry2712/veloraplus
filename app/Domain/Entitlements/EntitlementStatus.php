<?php

namespace App\Domain\Entitlements;

enum EntitlementStatus: string
{
    case Active = 'active';
    case ScheduledForRemoval = 'scheduled_for_removal';
    case Inactive = 'inactive';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function grantsAccess(): bool
    {
        return match ($this) {
            self::Active,
            self::ScheduledForRemoval => true,
            self::Inactive => false,
        };
    }
}
