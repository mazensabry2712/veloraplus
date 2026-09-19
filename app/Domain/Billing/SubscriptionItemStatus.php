<?php

namespace App\Domain\Billing;

enum SubscriptionItemStatus: string
{
    case Pending = 'pending';
    case Trialing = 'trialing';
    case Active = 'active';
    case ScheduledForRemoval = 'scheduled_for_removal';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}