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
        ?string $idempotencyKey = null,
        ?string $initiatedByAccountId = null,
    ): PlatformRefund {
        return DB::connection('central')->transaction(function () use (
            $payment,
            $amountMinor,
            $reason,
            $idempotencyKey,
            $initiatedByAccountId,
        ): PlatformRefund {
            $payment = PlatformPayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($payment->status !== PaymentStatus::Succeeded) {
                throw new DomainException('Only a successful payment can be refunded.');
            }

            $idempotencyKey = $idempotencyKey !== null ? trim($idempotencyKey) : null;

            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $existing = PlatformRefund::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ((string) $existing->payment_id !== (string) $payment->getKey()) {
                        throw new DomainException('Refund idempotency key was already used for another payment.');
                    }

                    return $existing;
                }
            } else {
                $idempotencyKey = null;
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
                'idempotency_key' => $idempotencyKey,
                'amount_minor' => $amountMinor,
                'currency' => $payment->currency,
                'status' => RefundStatus::Pending,
                'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
                'initiated_by_account_id' => $initiatedByAccountId,
                'metadata' => null,
            ]);
        });
    }

    public function markSucceeded(
        PlatformRefund $refund,
        ?string $providerRefundId = null,
        array $providerMetadata = [],
    ): PlatformRefund {
        return DB::connection('central')->transaction(function () use ($refund, $providerRefundId, $providerMetadata): PlatformRefund {
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
                'provider_refund_id' => $providerRefundId ?: $refund->provider_refund_id,
                'processed_at' => CarbonImmutable::now(),
                'metadata' => array_merge($refund->metadata ?? [], $providerMetadata),
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

    public function markFailed(
        PlatformRefund $refund,
        string $reason,
        array $providerMetadata = [],
    ): PlatformRefund {
        return DB::connection('central')->transaction(function () use ($refund, $reason, $providerMetadata): PlatformRefund {
            $refund = PlatformRefund::query()->lockForUpdate()->findOrFail($refund->getKey());

            if ($refund->status === RefundStatus::Failed) {
                return $refund;
            }

            if ($refund->status !== RefundStatus::Pending) {
                throw new DomainException('Only a pending refund can fail.');
            }

            $refund->update([
                'status' => RefundStatus::Failed,
                'metadata' => array_merge($refund->metadata ?? [], [
                    'failure_reason' => $reason,
                    ...$providerMetadata,
                ]),
            ]);

            $this->audit->record($refund->tenant, 'refund.failed', $refund, [
                'payment_id' => $refund->payment_id,
                'reason' => $reason,
            ]);

            return $refund->refresh();
        });
    }
}
