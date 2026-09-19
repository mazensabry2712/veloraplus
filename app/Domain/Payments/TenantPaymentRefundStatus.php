<?php

namespace App\Domain\Payments;

enum TenantPaymentRefundStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
