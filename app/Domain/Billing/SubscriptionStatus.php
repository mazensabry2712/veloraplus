<?php

namespace App\Domain\Billing;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case PastDue = 'past_due';
    case Grace = 'grace';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}