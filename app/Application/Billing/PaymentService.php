<?php

namespace App\Application\Billing;

use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\PaymentStatus;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PaymentService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly SubscriptionService $subscriptions,
        private readonly BillingAuditLogger $audit,
    ) {
    }

    public function createPending(
        PlatformInvoice $invoice,
        ?string $provider = null,
        ?string $providerPaymentId = null,
        ?string $providerEventId = null,
    ): PlatformPayment {
        if ($invoice->status !== InvoiceStatus::Open) {
            throw new DomainException('Only open invoices can receive payment attempts.');
        }

        return PlatformPayment::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'invoice_id' => $invoice->getKey(),
            'provider' => $provider,
            'provider_payment_id' => $providerPaymentId,
            'provider_event_id' => $providerEventId,
            'amount_minor' => $invoice->total_minor,
            'currency' => $invoice->currency,
            'status' => PaymentStatus::Pending,
            'metadata' => null,
        ]);
    }

    public function markSucceeded(
        PlatformPayment $payment,
        ?CarbonImmutable $paidAt = null,
        ?string $providerPaymentId = null,
        ?string $providerEventId = null,
        array $providerMetadata = [],
    ): PlatformPayment {
        $paidAt ??= CarbonImmutable::now();

        return DB::connection('central')->transaction(function () use (
            $payment,
            $paidAt,
            $providerPaymentId,
            $providerEventId,
            $providerMetadata,
        ): PlatformPayment {
            $payment = PlatformPayment::query()
                ->lockForUpdate()
                ->findOrFail($payment->getKey());

            if ($payment->status === PaymentStatus::Succeeded) {
                return $payment;
            }

            if ($payment->status !== PaymentStatus::Pending) {
                throw new DomainException('Only a pending payment can succeed.');
            }

            $invoice = PlatformInvoice::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($payment->invoice_id);

            if ($invoice->status === InvoiceStatus::Paid) {
                throw new DomainException('Invoice is already paid.');
            }

            if ($payment->amount_minor !== $invoice->total_minor || strtoupper($payment->currency) !== strtoupper($invoice->currency)) {
                throw new DomainException('Payment amount or currency does not match the invoice.');
            }

            $alreadyPaid = PlatformPayment::query()
                ->where('invoice_id', $invoice->getKey())
                ->where('status', PaymentStatus::Succeeded->value)
                ->whereKeyNot($payment->getKey())
                ->exists();

            if ($alreadyPaid) {
                throw new DomainException('Invoice already has a successful payment.');
            }

            $payment->update([
                'status' => PaymentStatus::Succeeded,
                'paid_at' => $paidAt,
                'provider_payment_id' => $providerPaymentId ?: $payment->provider_payment_id,
                'provider_event_id' => $providerEventId ?: $payment->provider_event_id,
                'metadata' => array_merge($payment->metadata ?? [], $providerMetadata),
            ]);

            $this->invoices->markPaid($invoice, $paidAt);

            if ($invoice->subscription_id !== null) {
                $this->subscriptions->activateInvoiceItems(
                    $invoice->fresh('items'),
                    $paidAt,
                );
            }

            $this->audit->record($invoice->tenant, 'payment.succeeded', $payment, [
                'invoice_id' => $invoice->getKey(),
            ]);

            return $payment->refresh();
        });
    }

    public function markFailed(
        PlatformPayment $payment,
        ?string $reason = null,
    ): PlatformPayment {
        return DB::connection('central')->transaction(function () use ($payment, $reason): PlatformPayment {
            $payment = PlatformPayment::query()
                ->lockForUpdate()
                ->findOrFail($payment->getKey());

            if ($payment->status !== PaymentStatus::Pending) {
                throw new DomainException('Only a pending payment can fail.');
            }

            $payment->update([
                'status' => PaymentStatus::Failed,
                'metadata' => array_merge($payment->metadata ?? [], ['failure_reason' => $reason]),
            ]);

            $this->audit->record($payment->tenant, 'payment.failed', $payment, [
                'reason' => $reason,
            ]);

            return $payment->refresh();
        });
    }
}
