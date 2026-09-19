<?php

namespace App\Domain\Billing;

enum SubscriptionStatus: string
{
    case PendingPayment = 'pending_payment';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Grace = 'grace';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function grantsEntitlements(): bool
    {
        return match ($this) {
            self::Trialing,
            self::Active,
            self::Grace => true,
            self::PendingPayment,
            self::PastDue,
            self::Suspended,
            self::Cancelled,
            self::Expired => false,
        };
    }
}
