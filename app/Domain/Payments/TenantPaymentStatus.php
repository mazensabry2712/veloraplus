<?php

namespace App\Domain\Payments;

enum TenantPaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
}
