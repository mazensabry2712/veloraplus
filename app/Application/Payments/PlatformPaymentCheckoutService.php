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

        $provider = strtolower((string) config('velora.payments.platform_provider'));
        $gateway = $this->gateways->platform($provider);

        if (! $gateway instanceof CheckoutGateway) {
            throw new DomainException("Payment provider [{$provider}] does not support checkout.");
        }

        $result = $gateway->createCheckout([
            'merchant_order_id' => $payment->getKey(),
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'merchant_redirect_url' => config('velora.payments.kashier.merchant_redirect_url'),
            'server_webhook_url' => config('velora.payments.kashier.webhook_url'),
            'customer' => $customer,
            'description' => 'VeloraPlus subscription '.$invoice->number,
        ]);

        $payment->update([
            'provider' => $provider,
            'metadata' => array_merge($payment->metadata ?? [], [
                'checkout' => [
                    'provider' => $provider,
                    'session_id' => $result['session_id'] ?? null,
                    'merchant_order_id' => $result['merchant_order_id'] ?? $payment->getKey(),
                ],
            ]),
        ]);

        return $result;
    }
}
