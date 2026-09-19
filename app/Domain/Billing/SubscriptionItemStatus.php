<?php

namespace App\Domain\Billing;

enum SubscriptionItemStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Scheduled = 'scheduled';
    case Inactive = 'inactive';
}
