<?php

namespace App\Policies;

use App\Models\PlatformAccount;
use App\Models\TenantPayment;

class TenantPaymentPolicy
{
    public function viewAny(PlatformAccount $account): bool
    {
        return $account->can('booking.payments.view');
    }

    public function view(PlatformAccount $account, TenantPayment $payment): bool
    {
        return $account->can('booking.payments.view');
    }

    public function create(PlatformAccount $account): bool
    {
        return $account->can('booking.payments.manage');
    }

    public function manage(PlatformAccount $account, TenantPayment $payment): bool
    {
        return $account->can('booking.payments.manage');
    }
}
