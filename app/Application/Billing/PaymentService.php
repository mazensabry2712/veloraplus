<?php

namespace App\Application\Billing;

use App\Domain\Billing\PaymentStatus;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use DomainException;
use Illuminate\Database\DatabaseManager;

final class PaymentService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly InvoiceService $invoices,
        private readonly BillingAuditService $audit,
        private readonly DatabaseManager $database,
    ) {
    }

    public function createPending(
        PlatformInvoice $invoice,
        int $amountMinor,
        string $idempotencyKey,
        ?string $gatewayKey = null,
    ): PlatformPayment {
        if ($amountMinor !== $invoice->total_minor) {
            throw new DomainException('Payment amount must equal the invoice total.');
        }

        if ($invoice->status->value === 'paid') {
            throw new DomainException('Cannot create a payment for an already paid invoice.');
        }

        return PlatformPayment::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->getKey(),
                'gateway_key' => $gatewayKey,
                'external_payment_id' => null,
                'amount_minor' => $amountMinor,
                'currency' => $invoice->currency,
                'status' => PaymentStatus::Pending,
                'paid_at' => null,
                'metadata' => null,
            ],
        );
    }

    public function succeed(
        PlatformPayment $payment,
        ?string $externalPaymentId = null,
    ): PlatformPayment {
        return $this->database->connection('central')->transaction(function () use ($payment, $externalPaymentId): PlatformPayment {
            $payment = PlatformPayment::query()
                ->with('invoice.subscription.tenant')
                ->lockForUpdate()
                ->findOrFail($payment->getKey());

            if ($payment->status === PaymentStatus::Succeeded) {
                return $payment;
            }

            if ($payment->status !== PaymentStatus::Pending) {
                throw new DomainException('Only pending payments can succeed.');
            }

            if ($payment->amount_minor !== $payment->invoice->total_minor) {
                throw new DomainException('Payment amount no longer matches the invoice total.');
            }

            $payment->update([
                'status' => PaymentStatus::Succeeded,
                'external_payment_id' => $externalPaymentId,
                'paid_at' => now()->toImmutable(),
            ]);

            $this->invoices->markPaid($payment->invoice);
            $this->subscriptions->activateAfterPayment($payment->invoice->subscription);

            $this->audit->record(
                $payment->invoice->tenant,
                'payment.succeeded',
                $payment,
                PaymentStatus::Pending->value,
                PaymentStatus::Succeeded->value,
            );

            return $payment->refresh();
        });
    }

    public function fail(PlatformPayment $payment): PlatformPayment
    {
        return $this->database->connection('central')->transaction(function () use ($payment): PlatformPayment {
            $payment = PlatformPayment::query()
                ->with('invoice.tenant')
                ->lockForUpdate()
                ->findOrFail($payment->getKey());

            if ($payment->status === PaymentStatus::Failed) {
                return $payment;
            }

            if ($payment->status !== PaymentStatus::Pending) {
                throw new DomainException('Only pending payments can fail.');
            }

            $payment->update(['status' => PaymentStatus::Failed]);
            $invoice = $payment->invoice->refresh();
            $invoice->update(['status' => 'past_due']);

            $this->audit->record(
                $payment->invoice->tenant,
                'payment.failed',
                $payment,
                PaymentStatus::Pending->value,
                PaymentStatus::Failed->value,
            );

            return $payment->refresh();
        });
    }
}