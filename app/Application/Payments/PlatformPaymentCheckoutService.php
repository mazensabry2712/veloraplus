<?php

namespace App\Application\Payments;

use App\Domain\Billing\PaymentStatus;
use App\Domain\Payments\Contracts\CheckoutGateway;
use App\Models\PlatformPayment;
use DomainException;

final class PlatformPaymentCheckoutService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
    ) {
    }

    /**
     * @param array<string, mixed> $customer
     * @return array<string, mixed>
     */
    public function create(PlatformPayment $payment, array $customer = []): array
    {
        $payment = PlatformPayment::query()->with('invoice')->findOrFail($payment->getKey());

        if ($payment->status !== PaymentStatus::Pending) {
            throw new DomainException('Only a pending platform payment can create a checkout.');
        }

        $invoice = $payment->invoice;

        if ($invoice === null) {
            throw new DomainException('Platform payment invoice could not be found.');
        }

        $existingCheckout = $payment->metadata['checkout'] ?? null;

        if (is_array($existingCheckout) && is_string($existingCheckout['checkout_url'] ?? null) && $existingCheckout['checkout_url'] !== '') {
            return [
                'provider' => $existingCheckout['provider'] ?? $payment->provider,
                'session_id' => $existingCheckout['session_id'] ?? null,
                'checkout_url' => $existingCheckout['checkout_url'],
                'merchant_order_id' => $existingCheckout['merchant_order_id'] ?? $payment->getKey(),
                'provider_payment_id' => $existingCheckout['provider_payment_id'] ?? null,
                'provider_order_id' => $existingCheckout['provider_order_id'] ?? null,
                'status' => 'REUSED',
            ];
        }

        $provider = strtolower((string) config('velora.payments.platform_provider'));
        $gateway = $this->gateways->platform($provider);

        if (! $gateway instanceof CheckoutGateway) {
            throw new DomainException("Payment provider [{$provider}] does not support checkout.");
        }

        $result = $gateway->createCheckout([
            'merchant_order_id' => $payment->getKey(),
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'customer' => $customer,
            'description' => 'VeloraPlus subscription '.$invoice->number,
        ]);

        $payment->update([
            'provider' => $provider,
            'metadata' => array_merge($payment->metadata ?? [], [
                'checkout' => [
                    'provider' => $provider,
                    'session_id' => $result['session_id'] ?? null,
                    'checkout_url' => $result['checkout_url'] ?? null,
                    'merchant_order_id' => $result['merchant_order_id'] ?? $payment->getKey(),
                    'provider_payment_id' => $result['provider_payment_id'] ?? null,
                    'provider_order_id' => $result['provider_order_id'] ?? null,
                ],
            ]),
        ]);

        return $result;
    }
}
