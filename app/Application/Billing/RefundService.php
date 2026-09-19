<?php

namespace App\Application\Billing;

use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\PaymentStatus;
use App\Domain\Billing\RefundStatus;
use App\Models\PlatformPayment;
use App\Models\PlatformRefund;
use App\Models\PlatformAccount;
use DomainException;
use Illuminate\Database\DatabaseManager;

final class RefundService
{
    public function __construct(
        private readonly BillingAuditService $audit,
        private readonly DatabaseManager $database,
    ) {
    }

    public function request(
        PlatformPayment $payment,
        int $amountMinor,
        string $reason,
        ?PlatformAccount $actor = null,
    ): PlatformRefund {
        if ($amountMinor < 1 || $amountMinor > $payment->amount_minor) {
            throw new DomainException('Refund amount is invalid.');
        }

        if ($payment->status !== PaymentStatus::Succeeded) {
            throw new DomainException('Only successful payments can be refunded.');
        }

        $refunded = $payment->refunds()
            ->where('status', RefundStatus::Succeeded->value)
            ->sum('amount_minor');

        if ($refunded + $amountMinor > $payment->amount_minor) {
            throw new DomainException('Refund amount exceeds the remaining refundable payment amount.');
        }

        return PlatformRefund::query()->create([
            'tenant_id' => $payment->tenant_id,
            'payment_id' => $payment->getKey(),
            'initiated_by_account_id' => $actor?->getKey(),
            'gateway_key' => $payment->gateway_key,
            'external_ref' => null,
            'amount_minor' => $amountMinor,
            'currency' => $payment->currency,
            'status' => RefundStatus::Pending,
            'reason' => $reason,
            'processed_at' => null,
            'metadata' => null,
        ]);
    }

    public function succeed(PlatformRefund $refund, ?string $externalRef = null): PlatformRefund
    {
        return $this->database->connection('central')->transaction(function () use ($refund, $externalRef): PlatformRefund {
            $refund = PlatformRefund::query()
                ->with('payment.invoice.tenant')
                ->lockForUpdate()
                ->findOrFail($refund->getKey());

            if ($refund->status === RefundStatus::Succeeded) {
                return $refund;
            }

            if ($refund->status !== RefundStatus::Pending) {
                throw new DomainException('Only pending refunds can succeed.');
            }

            $payment = PlatformPayment::query()
                ->with('invoice')
                ->lockForUpdate()
                ->findOrFail($refund->payment_id);

            $alreadyRefunded = $payment->refunds()
                ->where('status', RefundStatus::Succeeded->value)
                ->sum('amount_minor');

            if ($alreadyRefunded + $refund->amount_minor > $payment->amount_minor) {
                throw new DomainException('Refund would exceed the refundable payment balance.');
            }

            $refund->update([
                'status' => RefundStatus::Succeeded,
                'external_ref' => $externalRef,
                'processed_at' => now()->toImmutable(),
            ]);

            $newRefunded = $alreadyRefunded + $refund->amount_minor;
            $payment->update([
                'status' => $newRefunded === $payment->amount_minor
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded,
            ]);

            $payment->invoice->update([
                'status' => $newRefunded === $payment->amount_minor
                    ? InvoiceStatus::Refunded
                    : InvoiceStatus::PartiallyRefunded,
            ]);

            $this->audit->record(
                $refund->payment->invoice->tenant,
                'refund.succeeded',
                $refund,
                RefundStatus::Pending->value,
                RefundStatus::Succeeded->value,
            );

            return $refund->refresh();
        });
    }
}