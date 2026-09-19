<?php

namespace App\Application\Billing;

use App\Domain\Billing\PaymentStatus;
use App\Domain\Billing\RefundStatus;
use App\Models\PlatformPayment;
use App\Models\PlatformRefund;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RefundService
{
    public function __construct(
        private readonly BillingAuditLogger $audit,
    ) {
    }

    public function createPending(
        PlatformPayment $payment,
        int $amountMinor,
        ?string $reason = null,
    ): PlatformRefund {
        return DB::connection('central')->transaction(function () use ($payment, $amountMinor, $reason): PlatformRefund {
            $payment = PlatformPayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($payment->status !== PaymentStatus::Succeeded) {
                throw new DomainException('Only a successful payment can be refunded.');
            }

            if ($amountMinor < 1) {
                throw new DomainException('Refund amount must be positive.');
            }

            $reservedMinor = (int) $payment->refunds()
                ->whereIn('status', [
                    RefundStatus::Pending->value,
                    RefundStatus::Succeeded->value,
                ])
                ->sum('amount_minor');

            if ($reservedMinor + $amountMinor > $payment->amount_minor) {
                throw new DomainException('Refund exceeds the remaining payment amount.');
            }

            return PlatformRefund::query()->create([
                'tenant_id' => $payment->tenant_id,
                'payment_id' => $payment->getKey(),
                'amount_minor' => $amountMinor,
                'currency' => $payment->currency,
                'status' => RefundStatus::Pending,
                'reason' => $reason,
                'metadata' => null,
            ]);
        });
    }

    public function markSucceeded(
        PlatformRefund $refund,
        ?string $providerRefundId = null,
    ): PlatformRefund {
        return DB::connection('central')->transaction(function () use ($refund, $providerRefundId): PlatformRefund {
            $refund = PlatformRefund::query()->lockForUpdate()->findOrFail($refund->getKey());

            if ($refund->status === RefundStatus::Succeeded) {
                return $refund;
            }

            if ($refund->status !== RefundStatus::Pending) {
                throw new DomainException('Only a pending refund can succeed.');
            }

            $payment = PlatformPayment::query()->lockForUpdate()->findOrFail($refund->payment_id);
            $refundedBefore = (int) $payment->refunds()
                ->where('status', RefundStatus::Succeeded->value)
                ->whereKeyNot($refund->getKey())
                ->sum('amount_minor');

            if ($refundedBefore + $refund->amount_minor > $payment->amount_minor) {
                throw new DomainException('Refund exceeds the remaining payment amount.');
            }

            $refund->update([
                'status' => RefundStatus::Succeeded,
                'provider_refund_id' => $providerRefundId,
                'processed_at' => CarbonImmutable::now(),
            ]);

            $refundedMinor = $refundedBefore + $refund->amount_minor;

            if ($refundedMinor >= $payment->amount_minor) {
                $payment->update(['status' => PaymentStatus::Refunded]);
            }

            $this->audit->record($refund->tenant, 'refund.succeeded', $refund, [
                'payment_id' => $refund->payment_id,
                'amount_minor' => $refund->amount_minor,
            ]);

            return $refund->refresh();
        });
    }
}
