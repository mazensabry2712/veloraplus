<?php

namespace App\Application\Payments;

use App\Domain\Payments\Contracts\CheckoutGateway;
use App\Domain\Payments\Contracts\RefundGateway;
use App\Domain\Payments\Contracts\TransactionLookupGateway;
use App\Domain\Payments\TenantPaymentRefundStatus;
use App\Domain\Payments\TenantPaymentStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Appointment;
use App\Models\PaymentProviderAccount;
use App\Models\TenantPayment;
use App\Models\TenantPaymentRefund;
use App\Models\TenantPaymentWebhookEvent;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TenantPaymentManager
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly TenantContext $context,
    ) {
    }

    public function createCheckout(Appointment $appointment): TenantPayment
    {
        if (! $this->context->check()) {
            throw new DomainException('Tenant context is required for Tenant Payments.');
        }

        $tenant = $this->context->current();
        $shouldCreateCheckout = false;

        $payment = DB::transaction(function () use ($appointment, $tenant, &$shouldCreateCheckout): TenantPayment {
            $locked = Appointment::query()
                ->lockForUpdate()
                ->with(['items', 'customer'])
                ->find($appointment->getKey());

            if ($locked === null) {
                throw new DomainException('Appointment was not found in the current tenant.');
            }

            if ($locked->customer === null || $locked->customer->trashed()) {
                throw new DomainException('A valid active customer is required for Tenant Payments.');
            }

            if (in_array($locked->status?->value, ['cancelled', 'no_show'], true)) {
                throw new DomainException('Cancelled or no-show appointments cannot receive a payment checkout.');
            }

            $item = $locked->items->first();

            if ($item === null) {
                throw new DomainException('Appointment payment requires a service item.');
            }

            $existing = TenantPayment::query()
                ->where('appointment_id', $locked->getKey())
                ->whereIn('status', [
                    TenantPaymentStatus::Pending->value,
                    TenantPaymentStatus::Succeeded->value,
                ])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if (! in_array($locked->payment_status?->value, ['unpaid', 'failed'], true)) {
                throw new DomainException('Appointment is not eligible for a new payment checkout.');
            }

            $provider = strtolower(trim((string) config('velora.payments.tenant_provider')));

            if ($provider === '') {
                throw new DomainException('Tenant payment provider is not configured.');
            }

            $account = PaymentProviderAccount::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('provider', $provider)
                ->where('status', 'active')
                ->first();

            if ($account === null) {
                throw new DomainException('No active merchant account is configured for this Tenant.');
            }

            $amountMinor = $this->appointmentAmountMinor($locked);
            $currency = strtoupper((string) $item->currency);

            if ($amountMinor < 1) {
                throw new DomainException('Tenant payment checkout requires a positive appointment amount.');
            }

            $shouldCreateCheckout = true;

            $payment = TenantPayment::query()->create([
                'appointment_id' => $locked->getKey(),
                'customer_id' => $locked->customer_id,
                'provider' => $provider,
                'merchant_order_id' => $tenant->getKey().'.'.Str::ulid(),
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'status' => TenantPaymentStatus::Pending,
                'metadata' => [
                    'payment_provider_account_id' => $account->getKey(),
                    'payment_provider_account_reference' => $account->account_reference,
                ],
            ]);

            return $payment;
        });

        if (! $shouldCreateCheckout) {
            return $payment;
        }

        if (
            $payment->status !== TenantPaymentStatus::Pending
            || $payment->provider_session_id !== null
        ) {
            return $payment;
        }

        $accountId = (string) ($payment->metadata['payment_provider_account_id'] ?? '');

        $account = PaymentProviderAccount::query()
            ->whereKey($accountId)
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->first();

        if ($account === null) {
            $this->markFailed($payment, 'Merchant account became unavailable before checkout.');

            throw new DomainException('Tenant merchant account is no longer available.');
        }

        $gateway = $this->gateways->tenant($payment->provider);

        if (! $gateway instanceof CheckoutGateway) {
            $this->markFailed($payment, 'Tenant payment provider does not support checkout.');

            throw new DomainException('Tenant payment provider does not support checkout.');
        }

        $appointment = $payment->appointment()->with(['customer', 'items'])->firstOrFail();
        $customer = $appointment->customer;
        $item = $appointment->items->first();

        try {
            $checkout = $gateway->createCheckout([
                'amount_minor' => $payment->amount_minor,
                'currency' => $payment->currency,
                'merchant_order_id' => $payment->merchant_order_id,
                'payment_account' => [
                    'id' => $account->getKey(),
                    'reference' => $account->account_reference,
                    'credentials' => $account->encrypted_credentials ?? [],
                ],
                'customer' => [
                    'email' => $customer?->email,
                    'reference' => $customer?->getKey(),
                    'firstName' => $customer?->name,
                ],
                'description' => $item?->service_name.' booking payment',
            ]);
        } catch (\Throwable $exception) {
            $this->markFailed($payment, 'Tenant provider checkout failed.');

            throw $exception;
        }

        $payment->update([
            'provider_payment_id' => $checkout['provider_payment_id'] ?? null,
            'provider_order_id' => $checkout['provider_order_id'] ?? null,
            'provider_session_id' => $checkout['session_id'] ?? null,
            'metadata' => array_merge($payment->metadata ?? [], [
                'checkout' => $checkout,
            ]),
        ]);

        $payment->refresh();

        $appointment->forceFill([
            'payment_status' => 'pending',
        ])->save();

        return $payment;
    }

    public function markSucceeded(
        TenantPayment $payment,
        ?string $providerPaymentId = null,
        ?string $providerOrderId = null,
        array $metadata = [],
    ): TenantPayment {
        return DB::transaction(function () use ($payment, $providerPaymentId, $providerOrderId, $metadata): TenantPayment {
            $locked = TenantPayment::query()
                ->lockForUpdate()
                ->find($payment->getKey());

            if ($locked === null) {
                throw new DomainException('Tenant payment was not found.');
            }

            $this->markSucceededLocked(
                $locked,
                (string) ($providerPaymentId ?? ''),
                (string) ($providerOrderId ?? ''),
                $metadata,
            );

            return $locked->refresh();
        });
    }

    public function markFailed(TenantPayment $payment, string $reason): TenantPayment
    {
        return DB::transaction(function () use ($payment, $reason): TenantPayment {
            $locked = TenantPayment::query()
                ->lockForUpdate()
                ->find($payment->getKey());

            if ($locked === null) {
                throw new DomainException('Tenant payment was not found.');
            }

            $this->markFailedLocked($locked, $reason);

            return $locked->refresh();
        });
    }


    /**
     * Process a cryptographically verified Tenant webhook inside the current Tenant database.
     *
     * @param array<string, mixed> $event
     * @param array<string, mixed> $payload
     * @return array{duplicate: bool, status: string}
     */
    public function processVerifiedWebhook(
        TenantPayment $payment,
        array $event,
        array $payload,
        string $providerEventId,
        string $payloadHash,
    ): array {
        return DB::transaction(function () use ($payment, $event, $payload, $providerEventId, $payloadHash): array {
            $locked = TenantPayment::query()
                ->lockForUpdate()
                ->find($payment->getKey());

            if ($locked === null) {
                throw new DomainException('Tenant payment was not found.');
            }

            TenantPaymentWebhookEvent::query()->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'provider' => (string) ($event['provider'] ?? $locked->provider),
                'provider_event_id' => $providerEventId,
                'event_type' => (string) ($event['event'] ?? 'unknown'),
                'status' => 'received',
                'merchant_order_id' => $event['merchant_order_id'] ?? $locked->merchant_order_id,
                'transaction_id' => $event['transaction_id'] ?? null,
                'received_at' => now(),
                'payload_hash' => $payloadHash,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);

            $stored = TenantPaymentWebhookEvent::query()
                ->where('provider', $locked->provider)
                ->where('provider_event_id', $providerEventId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $stored->payload_hash !== $payloadHash) {
                throw new DomainException('Tenant payment webhook payload does not match the existing event.');
            }

            if ($stored->processed_at !== null) {
                return ['duplicate' => true, 'status' => $stored->status];
            }

            if (($event['event'] ?? null) !== 'pay') {
                $stored->update([
                    'status' => 'ignored',
                    'processed_at' => now(),
                ]);

                return ['duplicate' => false, 'status' => 'ignored'];
            }

            $this->assertVerifiedEventMatchesPayment($locked, $event);

            $status = strtoupper((string) ($event['status'] ?? ''));

            if ($status === 'SUCCESS') {
                $this->markSucceededLocked(
                    $locked,
                    (string) ($event['transaction_id'] ?? ''),
                    (string) ($event['kashier_order_id'] ?? ''),
                    [
                        'verification_source' => 'tenant_webhook',
                        'provider_event_id' => $providerEventId,
                        'kashier_order_id' => $event['kashier_order_id'] ?? null,
                        'merchant_order_id' => $locked->merchant_order_id,
                        'order_reference' => $event['order_reference'] ?? null,
                        'webhook_status' => $status,
                    ],
                );
            } elseif ($status === 'FAILURE') {
                if ($locked->status === TenantPaymentStatus::Pending) {
                    $this->markFailedLocked(
                        $locked,
                        'Tenant provider webhook failure',
                    );
                }
            } elseif ($status !== 'PENDING') {
                throw new DomainException("Unsupported Tenant payment webhook status [{$status}].");
            }

            $stored->update([
                'status' => $status === 'SUCCESS' ? 'processed' : 'ignored',
                'processed_at' => now(),
            ]);

            return ['duplicate' => false, 'status' => $status];
        });
    }

    public function reconcile(TenantPayment $payment): TenantPayment
    {
        if (! $this->context->check()) {
            throw new DomainException('Tenant context is required for Tenant Payment reconciliation.');
        }

        $tenant = $this->context->current();

        if ($payment->provider === '' || $payment->status !== TenantPaymentStatus::Pending) {
            return $payment->refresh();
        }

        $account = $this->paymentAccountFor($payment, $tenant->getKey());

        $gateway = $this->gateways->tenant($payment->provider);

        if (! $gateway instanceof TransactionLookupGateway) {
            throw new DomainException('Tenant payment provider does not support transaction lookup.');
        }

        $reference = $payment->provider_session_id
            ?: $payment->provider_payment_id
            ?: $payment->provider_order_id;

        if ($reference === null || trim($reference) === '') {
            throw new DomainException('Tenant payment does not have a provider reference for reconciliation.');
        }

        $result = $gateway->retrieveTransaction($reference, $account->encrypted_credentials ?? []);

        $status = strtoupper((string) ($result['status'] ?? ''));

        if ($status === 'SUCCESS') {
            $this->assertVerifiedAmountAndCurrency(
                $payment,
                (string) ($result['amount'] ?? ''),
                (string) ($result['currency'] ?? ''),
            );

            return $this->markSucceeded(
                $payment,
                (string) ($result['transaction_id'] ?? ''),
                (string) ($result['order_id'] ?? ''),
                ['verification_source' => 'reconciliation'],
            );
        }

        if ($status === 'FAILURE') {
            return $this->markFailed($payment, 'Tenant provider reconciliation failure');
        }

        return $payment->refresh();
    }

    public function refund(
        TenantPayment $payment,
        int $amountMinor,
        string $reason,
        ?string $idempotencyKey = null,
    ): TenantPaymentRefund {
        if (! $this->context->check()) {
            throw new DomainException('Tenant context is required for Tenant Payment refunds.');
        }

        $tenant = $this->context->current();
        $shouldCallProvider = false;

        $refund = DB::transaction(function () use ($payment, $amountMinor, $reason, $idempotencyKey, $tenant, &$shouldCallProvider): TenantPaymentRefund {
            $locked = TenantPayment::query()
                ->lockForUpdate()
                ->find($payment->getKey());

            if ($locked === null) {
                throw new DomainException('Tenant payment was not found.');
            }

            if ($idempotencyKey !== null) {
                $existing = TenantPaymentRefund::query()
                    ->where('idempotency_key', trim($idempotencyKey))
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ((string) $existing->tenant_payment_id !== (string) $locked->getKey()) {
                        throw new DomainException('Refund idempotency key was already used for another payment.');
                    }

                    return $existing;
                }
            }

            if ($locked->status !== TenantPaymentStatus::Succeeded) {
                throw new DomainException('Only succeeded Tenant Payments can be refunded.');
            }

            if ($amountMinor < 1) {
                throw new DomainException('Refund amount must be positive.');
            }

            $alreadyReserved = (int) TenantPaymentRefund::query()
                ->where('tenant_payment_id', $locked->getKey())
                ->whereIn('status', [
                    TenantPaymentRefundStatus::Pending->value,
                    TenantPaymentRefundStatus::Succeeded->value,
                ])
                ->sum('amount_minor');

            $remaining = $locked->amount_minor - $alreadyReserved;

            if ($amountMinor > $remaining) {
                throw new DomainException('Refund amount exceeds the remaining refundable payment amount.');
            }

            $accountId = (string) ($locked->metadata['payment_provider_account_id'] ?? '');

            if ($accountId === '') {
                throw new DomainException('Tenant payment merchant account reference is missing.');
            }

            $account = PaymentProviderAccount::query()
                ->whereKey($accountId)
                ->where('tenant_id', $tenant->getKey())
                ->whereIn('status', ['active', 'inactive'])
                ->first();

            if ($account === null) {
                throw new DomainException('Tenant payment merchant account was not found.');
            }

            $refund = TenantPaymentRefund::query()->create([
                'tenant_payment_id' => $locked->getKey(),
                'idempotency_key' => $idempotencyKey !== null ? trim($idempotencyKey) : null,
                'amount_minor' => $amountMinor,
                'currency' => $locked->currency,
                'status' => TenantPaymentRefundStatus::Pending,
                'reason' => trim($reason) !== '' ? trim($reason) : null,
                'requested_at' => now(),
                'metadata' => [
                    'payment_provider_account_id' => $account->getKey(),
                    'payment_provider_account_reference' => $account->account_reference,
                ],
            ]);

            $shouldCallProvider = true;

            return $refund;
        });

        if (! $shouldCallProvider) {
            return $refund;
        }

        $accountId = (string) ($refund->metadata['payment_provider_account_id'] ?? '');
        $account = PaymentProviderAccount::query()
            ->whereKey($accountId)
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('status', ['active', 'inactive'])
            ->first();

        if ($account === null) {
            $this->markRefundFailed($refund, 'Tenant merchant account was unavailable during refund.');

            throw new DomainException('Tenant merchant account was unavailable during refund.');
        }

        $gateway = $this->gateways->tenant($payment->provider);

        if (! $gateway instanceof RefundGateway) {
            $this->markRefundFailed($refund, 'Tenant payment provider does not support refunds.');

            throw new DomainException('Tenant payment provider does not support refunds.');
        }

        $providerOrderId = (string) (
            $payment->provider_order_id
            ?: ($payment->metadata['kashier_order_id'] ?? '')
        );

        if ($providerOrderId === '') {
            $this->markRefundFailed($refund, 'Provider order reference is missing for refund.');

            throw new DomainException('Provider order reference is missing for refund.');
        }

        try {
            $response = $gateway->createRefund([
                'amount_minor' => $refund->amount_minor,
                'currency' => $refund->currency,
                'kashier_order_id' => $providerOrderId,
                'transaction_id' => $payment->provider_payment_id,
                'reason' => $refund->reason,
                'payment_account' => [
                    'id' => $account->getKey(),
                    'reference' => $account->account_reference,
                    'credentials' => $account->encrypted_credentials ?? [],
                ],
            ]);
        } catch (\Throwable $exception) {
            $this->markRefundFailed($refund, 'Tenant provider refund failed.');

            throw $exception;
        }

        $status = strtoupper((string) ($response['status'] ?? 'PENDING'));
        $providerRefundId = isset($response['provider_refund_id'])
            ? (string) $response['provider_refund_id']
            : null;

        if (in_array($status, ['SUCCESS', 'SUCCEEDED', 'COMPLETED'], true)) {
            return $this->markRefundSucceeded($refund, $providerRefundId, $response);
        }

        $refund->update([
            'metadata' => array_merge($refund->metadata ?? [], [
                'provider_response' => $response,
            ]),
        ]);

        return $refund->refresh();
    }

    public function markRefundSucceeded(
        TenantPaymentRefund $refund,
        ?string $providerRefundId = null,
        array $metadata = [],
    ): TenantPaymentRefund {
        return DB::transaction(function () use ($refund, $providerRefundId, $metadata): TenantPaymentRefund {
            $locked = TenantPaymentRefund::query()
                ->lockForUpdate()
                ->find($refund->getKey());

            if ($locked === null) {
                throw new DomainException('Tenant payment refund was not found.');
            }

            if ($locked->status === TenantPaymentRefundStatus::Succeeded) {
                return $locked->refresh();
            }

            if ($locked->status !== TenantPaymentRefundStatus::Pending) {
                throw new DomainException('Only pending refunds can succeed.');
            }

            $locked->update([
                'status' => TenantPaymentRefundStatus::Succeeded,
                'provider_refund_id' => $providerRefundId ?: $locked->provider_refund_id,
                'completed_at' => now(),
                'metadata' => array_merge($locked->metadata ?? [], $metadata),
            ]);

            $payment = TenantPayment::query()
                ->lockForUpdate()
                ->find($locked->tenant_payment_id);

            if ($payment !== null) {
                $refundedTotal = (int) TenantPaymentRefund::query()
                    ->where('tenant_payment_id', $payment->getKey())
                    ->where('status', TenantPaymentRefundStatus::Succeeded->value)
                    ->sum('amount_minor');

                if ($refundedTotal >= $payment->amount_minor) {
                    $payment->update([
                        'status' => TenantPaymentStatus::Refunded,
                        'refunded_at' => now(),
                    ]);

                    if ($payment->appointment_id !== null) {
                        Appointment::query()
                            ->whereKey($payment->appointment_id)
                            ->update(['payment_status' => 'refunded']);
                    }
                }
            }

            return $locked->refresh();
        });
    }

    public function markRefundFailed(TenantPaymentRefund $refund, string $reason): TenantPaymentRefund
    {
        return DB::transaction(function () use ($refund, $reason): TenantPaymentRefund {
            $locked = TenantPaymentRefund::query()
                ->lockForUpdate()
                ->find($refund->getKey());

            if ($locked === null) {
                throw new DomainException('Tenant payment refund was not found.');
            }

            if ($locked->status === TenantPaymentRefundStatus::Failed) {
                return $locked->refresh();
            }

            if ($locked->status !== TenantPaymentRefundStatus::Pending) {
                throw new DomainException('Only pending refunds can fail.');
            }

            $locked->update([
                'status' => TenantPaymentRefundStatus::Failed,
                'failed_at' => now(),
                'metadata' => array_merge($locked->metadata ?? [], [
                    'failure_reason' => $reason,
                ]),
            ]);

            return $locked->refresh();
        });
    }

    private function markSucceededLocked(
        TenantPayment $payment,
        string $providerPaymentId,
        string $providerOrderId,
        array $metadata = [],
    ): TenantPayment {
        if ($payment->status === TenantPaymentStatus::Succeeded) {
            return $payment;
        }

        if ($payment->status !== TenantPaymentStatus::Pending) {
            throw new DomainException('Only pending Tenant Payments can succeed.');
        }

        $payment->update([
            'provider_payment_id' => $providerPaymentId !== '' ? $providerPaymentId : $payment->provider_payment_id,
            'provider_order_id' => $providerOrderId !== '' ? $providerOrderId : $payment->provider_order_id,
            'status' => TenantPaymentStatus::Succeeded,
            'paid_at' => now(),
            'metadata' => array_merge($payment->metadata ?? [], $metadata),
        ]);

        if ($payment->appointment_id !== null) {
            Appointment::query()
                ->whereKey($payment->appointment_id)
                ->update(['payment_status' => 'paid']);
        }

        return $payment;
    }

    private function markFailedLocked(TenantPayment $payment, string $reason): TenantPayment
    {
        if ($payment->status === TenantPaymentStatus::Failed) {
            return $payment;
        }

        if ($payment->status !== TenantPaymentStatus::Pending) {
            throw new DomainException('Only pending Tenant Payments can fail.');
        }

        $payment->update([
            'status' => TenantPaymentStatus::Failed,
            'failed_at' => now(),
            'metadata' => array_merge($payment->metadata ?? [], [
                'failure_reason' => $reason,
            ]),
        ]);

        if ($payment->appointment_id !== null) {
            Appointment::query()
                ->whereKey($payment->appointment_id)
                ->update(['payment_status' => 'failed']);
        }

        return $payment;
    }

    private function paymentAccountFor(TenantPayment $payment, string $tenantId): PaymentProviderAccount
    {
        $accountId = (string) ($payment->metadata['payment_provider_account_id'] ?? '');

        if ($accountId === '') {
            throw new DomainException('Tenant payment merchant account reference is missing.');
        }

        $account = PaymentProviderAccount::query()
            ->whereKey($accountId)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'inactive'])
            ->first();

        if ($account === null) {
            throw new DomainException('Tenant payment merchant account was not found.');
        }

        return $account;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function assertVerifiedEventMatchesPayment(TenantPayment $payment, array $event): void
    {
        if (
            isset($event['merchant_order_id'])
            && trim((string) $event['merchant_order_id']) !== ''
            && (string) $event['merchant_order_id'] !== (string) $payment->merchant_order_id
        ) {
            throw new DomainException('Tenant payment webhook merchant order does not match the payment.');
        }

        if (strtoupper((string) ($event['currency'] ?? '')) !== strtoupper((string) $payment->currency)) {
            throw new DomainException('Tenant payment webhook currency does not match the payment.');
        }

        $this->assertVerifiedAmountAndCurrency(
            $payment,
            (string) ($event['amount'] ?? ''),
            (string) ($event['currency'] ?? ''),
        );
    }

    private function assertVerifiedAmountAndCurrency(
        TenantPayment $payment,
        string $amount,
        string $currency,
    ): void {
        if (strtoupper($currency) !== strtoupper($payment->currency)) {
            throw new DomainException('Tenant payment currency does not match.');
        }

        if ($this->decimalToMinor($amount) !== $payment->amount_minor) {
            throw new DomainException('Tenant payment amount does not match.');
        }
    }

    private function decimalToMinor(string $amount): int
    {
        $amount = trim($amount);

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new DomainException('Tenant payment amount has an invalid format.');
        }

        $whole = (int) $matches[1];

        if ($whole > intdiv(PHP_INT_MAX, 100)) {
            throw new DomainException('Tenant payment amount exceeds supported range.');
        }

        return ($whole * 100) + (int) str_pad($matches[2] ?? '', 2, '0', STR_PAD_RIGHT);
    }

    private function appointmentAmountMinor(Appointment $appointment): int
    {
        return (int) $appointment->items->sum(
            fn ($item): int => ((int) $item->line_total_minor) * max(1, (int) $item->quantity),
        );
    }
}
