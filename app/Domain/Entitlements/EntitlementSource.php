<?php

namespace App\Domain\Entitlements;

enum EntitlementSource: string
{
    case Subscription = 'subscription';
    case Bundle = 'bundle';
    case Dependency = 'dependency';
    case Trial = 'trial';
    case Manual = 'manual';

    public function priority(): int
    {
        return match ($this) {
            self::Dependency => 10,
            self::Trial => 20,
            self::Bundle => 30,
            self::Subscription => 40,
            self::Manual => 50,
        };
    }
}
