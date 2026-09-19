<?php

namespace App\Application\Payments;

use App\Domain\Payments\Contracts\CheckoutGateway;
use App\Domain\Payments\TenantPaymentStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Appointment;
use App\Models\PaymentProviderAccount;
use App\Models\TenantPayment;
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

            if ($locked->status === TenantPaymentStatus::Succeeded) {
                return $locked->refresh();
            }

            if ($locked->status !== TenantPaymentStatus::Pending) {
                throw new DomainException('Only pending Tenant Payments can succeed.');
            }

            $locked->update([
                'provider_payment_id' => $providerPaymentId ?: $locked->provider_payment_id,
                'provider_order_id' => $providerOrderId ?: $locked->provider_order_id,
                'status' => TenantPaymentStatus::Succeeded,
                'paid_at' => now(),
                'metadata' => array_merge($locked->metadata ?? [], $metadata),
            ]);

            if ($locked->appointment_id !== null) {
                Appointment::query()
                    ->whereKey($locked->appointment_id)
                    ->update(['payment_status' => 'paid']);
            }

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

            if ($locked->status === TenantPaymentStatus::Failed) {
                return $locked->refresh();
            }

            if ($locked->status !== TenantPaymentStatus::Pending) {
                throw new DomainException('Only pending Tenant Payments can fail.');
            }

            $locked->update([
                'status' => TenantPaymentStatus::Failed,
                'failed_at' => now(),
                'metadata' => array_merge($locked->metadata ?? [], [
                    'failure_reason' => $reason,
                ]),
            ]);

            if ($locked->appointment_id !== null) {
                Appointment::query()
                    ->whereKey($locked->appointment_id)
                    ->update(['payment_status' => 'failed']);
            }

            return $locked->refresh();
        });
    }

    private function appointmentAmountMinor(Appointment $appointment): int
    {
        return (int) $appointment->items->sum(
            fn ($item): int => ((int) $item->line_total_minor) * max(1, (int) $item->quantity),
        );
    }
}
