<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantContext;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\PlatformRefund;
use App\Models\PlatformAccount;
use App\Models\Subscription;

class PlatformBillingPolicy
{
    public function view(PlatformAccount $account): bool
    {
        $context = app(TenantContext::class);

        return $context->check() && $account->can('billing.view');
    }

    public function manage(PlatformAccount $account): bool
    {
        $context = app(TenantContext::class);

        return $context->check() && $account->can('billing.manage');
    }

    public function viewSubscription(PlatformAccount $account, Subscription $subscription): bool
    {
        return $this->belongsToCurrentTenant($subscription) && $account->can('billing.view');
    }

    public function manageSubscription(PlatformAccount $account, Subscription $subscription): bool
    {
        return $this->belongsToCurrentTenant($subscription) && $account->can('billing.manage');
    }

    public function viewInvoice(PlatformAccount $account, PlatformInvoice $invoice): bool
    {
        return $this->belongsToCurrentTenant($invoice) && $account->can('billing.view');
    }

    public function viewPayment(PlatformAccount $account, PlatformPayment $payment): bool
    {
        return $this->belongsToCurrentTenant($payment) && $account->can('billing.view');
    }

    public function manageInvoice(PlatformAccount $account, PlatformInvoice $invoice): bool
    {
        return $this->belongsToCurrentTenant($invoice) && $account->can('billing.manage');
    }

    public function viewRefund(PlatformAccount $account, PlatformRefund $refund): bool
    {
        return $this->belongsToCurrentTenant($refund) && $account->can('billing.view');
    }

    private function belongsToCurrentTenant(object $model): bool
    {
        $context = app(TenantContext::class);
        $tenant = $context->get();

        return $context->check()
            && isset($model->tenant_id)
            && $tenant !== null
            && (string) $model->tenant_id === (string) $tenant->getKey();
    }
}
