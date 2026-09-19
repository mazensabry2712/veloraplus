<?php

namespace App\Application\Billing;

use App\Application\Payments\PaymentGatewayManager;
use App\Domain\Billing\RefundStatus;
use App\Domain\Payments\Contracts\RefundGateway;
use App\Domain\Tenancy\TenantContext;
use App\Models\PlatformPayment;
use App\Models\PlatformRefund;
use DomainException;

final class PlatformBillingRefundManager
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly RefundService $refunds,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function request(
        PlatformPayment $payment,
        int $amountMinor,
        string $reason,
        ?string $idempotencyKey = null,
        ?string $initiatedByAccountId = null,
    ): PlatformRefund {
        $tenant = $this->tenantContext->current();

        if (! $this->tenantContext->check() || $tenant === null) {
            throw new DomainException('Tenant billing context is required.');
        }

        if ((string) $payment->tenant_id !== (string) $tenant->getKey()) {
            throw new DomainException('Payment does not belong to the current tenant.');
        }

        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            $existing = PlatformRefund::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('idempotency_key', trim($idempotencyKey))
                ->first();

            if ($existing !== null) {
                if ((string) $existing->payment_id !== (string) $payment->getKey()) {
                    throw new DomainException('Refund idempotency key was already used for another payment.');
                }

                return $existing;
            }
        }

        $refund = $this->refunds->createPending(
            payment: $payment,
            amountMinor: $amountMinor,
            reason: $reason,
            idempotencyKey: $idempotencyKey,
            initiatedByAccountId: $initiatedByAccountId,
        );

        if ($refund->status !== RefundStatus::Pending) {
            return $refund;
        }

        $provider = strtolower(trim((string) ($payment->provider ?: config('velora.payments.platform_provider'))));

        if ($provider === '') {
            $this->refunds->markFailed($refund, 'Platform payment provider is not configured.');

            throw new DomainException('Platform payment provider is not configured.');
        }

        $gateway = $this->gateways->platform($provider);

        if (! $gateway instanceof RefundGateway) {
            $this->refunds->markFailed($refund, 'Platform payment provider does not support refunds.');

            throw new DomainException('Platform payment provider does not support refunds.');
        }

        $providerOrderId = trim((string) (
            data_get($payment->metadata, 'checkout.provider_order_id')
            ?: data_get($payment->metadata, 'kashier_order_id')
            ?: ''
        ));

        if ($providerOrderId === '') {
            $this->refunds->markFailed($refund, 'Provider order reference is missing for refund.');

            throw new DomainException('Provider order reference is missing for refund.');
        }

        try {
            $response = $gateway->createRefund([
                'amount_minor' => $refund->amount_minor,
                'currency' => $refund->currency,
                'provider_order_id' => $providerOrderId,
                'kashier_order_id' => $providerOrderId,
                'transaction_id' => $payment->provider_payment_id,
                'reason' => $refund->reason,
                'merchant_order_id' => $payment->getKey(),
            ]);
        } catch (\Throwable $exception) {
            $this->refunds->markFailed($refund, 'Platform provider refund failed.');

            throw $exception;
        }

        $status = strtoupper((string) ($response['status'] ?? 'PENDING'));
        $providerRefundId = isset($response['provider_refund_id'])
            ? (string) $response['provider_refund_id']
            : null;

        if (in_array($status, ['SUCCESS', 'SUCCEEDED', 'COMPLETED'], true)) {
            return $this->refunds->markSucceeded($refund, $providerRefundId, [
                'provider_response' => $response,
            ]);
        }

        $refund->update([
            'metadata' => array_merge($refund->metadata ?? [], [
                'provider_response' => $response,
            ]),
        ]);

        return $refund->refresh();
    }
}
