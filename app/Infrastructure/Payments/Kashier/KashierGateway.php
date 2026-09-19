<?php

namespace App\Infrastructure\Payments\Kashier;

use App\Domain\Payments\Contracts\CheckoutGateway;
use App\Domain\Payments\Contracts\PaymentVerificationGateway;
use App\Domain\Payments\Contracts\PlatformPaymentGateway;
use App\Domain\Payments\Contracts\RefundGateway;
use App\Domain\Payments\Contracts\TenantPaymentGateway;
use App\Domain\Payments\Contracts\TransactionLookupGateway;
use App\Domain\Payments\Contracts\WebhookGateway;
use DomainException;

final class KashierGateway implements
    PlatformPaymentGateway,
    TenantPaymentGateway,
    CheckoutGateway,
    PaymentVerificationGateway,
    RefundGateway,
    WebhookGateway,
    TransactionLookupGateway
{
    public function __construct(
        private readonly KashierClient $client,
        private readonly KashierWebhookVerifier $verifier,
    ) {
    }

    public function provider(): string
    {
        return 'kashier';
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function createCheckout(array $context): array
    {
        $currency = strtoupper((string) ($context['currency'] ?? ''));
        $amountMinor = (int) ($context['amount_minor'] ?? 0);
        $merchantOrderId = trim((string) ($context['merchant_order_id'] ?? ''));
        $credentials = $this->credentialsForContext($context);

        if (! in_array($currency, ['EGP', 'USD', 'EUR', 'GBP'], true)) {
            throw new DomainException('Kashier Phase 6 supports EGP, USD, EUR, and GBP.');
        }

        if ($amountMinor < 1) {
            throw new DomainException('Kashier checkout amount must be positive.');
        }

        if ($merchantOrderId === '') {
            throw new DomainException('Kashier checkout requires a merchant order identifier.');
        }

        $payload = [
            'expireAt' => now()->addMinutes(30)->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'maxFailureAttempts' => max(1, (int) config('velora.payments.kashier.max_failure_attempts', 3)),
            'paymentType' => 'credit',
            'amount' => $this->minorToDecimal($amountMinor),
            'currency' => $currency,
            'order' => $merchantOrderId,
            'merchantRedirect' => (string) ($credentials['merchant_redirect_url'] ?? config('velora.payments.kashier.merchant_redirect_url')),
            'display' => 'en',
            'type' => 'one-time',
            'allowedMethods' => (string) config('velora.payments.kashier.allowed_methods', 'card,wallet'),
            'merchantId' => (string) ($credentials['merchant_id'] ?? config('velora.payments.kashier.merchant_id')),
            'interactionSource' => 'ECOMMERCE',
            'serverWebhook' => (string) ($credentials['webhook_url'] ?? config('velora.payments.kashier.webhook_url')),
            'failureRedirect' => false,
            'customer' => array_filter([
                'email' => $context['customer']['email'] ?? null,
                'reference' => $context['customer']['reference'] ?? $merchantOrderId,
                'firstName' => $context['customer']['firstName'] ?? null,
                'lastName' => $context['customer']['lastName'] ?? null,
                'mobilePhone' => $context['customer']['mobilePhone'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
            'description' => $context['description'] ?? null,
        ];

        if ($payload['merchantRedirect'] === '' || $payload['serverWebhook'] === '' || $payload['merchantId'] === '') {
            throw new DomainException('Kashier checkout configuration is incomplete.');
        }

        $response = $this->client->createPaymentSession($payload, $credentials ?: null);

        $sessionId = (string) ($response['_id'] ?? '');
        $checkoutUrl = (string) ($response['sessionUrl'] ?? '');

        if ($sessionId === '' || $checkoutUrl === '') {
            throw new DomainException('Kashier did not return a valid payment session.');
        }

        return [
            'provider' => $this->provider(),
            'session_id' => $sessionId,
            'checkout_url' => $checkoutUrl,
            'merchant_order_id' => $merchantOrderId,
            'provider_payment_id' => $response['paymentId'] ?? $response['transactionId'] ?? null,
            'provider_order_id' => $response['orderId'] ?? null,
            'status' => strtoupper((string) ($response['status'] ?? 'CREATED')),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function verifyPayment(array $payload): array
    {
        $data = isset($payload['data']) && is_array($payload['data'])
            ? $payload['data']
            : $payload;

        return [
            'provider' => $this->provider(),
            'status' => strtoupper((string) ($data['status'] ?? '')),
            'transaction_id' => $data['transactionId'] ?? null,
            'order_id' => $data['orderId'] ?? null,
            'merchant_order_id' => $data['merchantOrderId'] ?? null,
            'order_reference' => $data['orderReference'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function createRefund(array $context): array
    {
        $orderId = trim((string) ($context['kashier_order_id'] ?? ''));
        $amountMinor = (int) ($context['amount_minor'] ?? 0);
        $credentials = $this->credentialsForContext($context);

        if ($orderId === '' || $amountMinor < 1) {
            throw new DomainException('Kashier refund requires order id and positive amount.');
        }

        $transaction = [
            'amount' => $this->minorToDecimal($amountMinor),
        ];

        if (! empty($context['transaction_id'])) {
            $transaction['targetTransactionId'] = (string) $context['transaction_id'];
        }

        $payload = [
            'apiOperation' => 'REFUND',
            'reason' => $context['reason'] ?? null,
            'transaction' => $transaction,
        ];

        if ($payload['reason'] === null) {
            unset($payload['reason']);
        }

        $response = $this->client->refundOrder($orderId, $payload, $credentials ?: null);

        return [
            'provider' => $this->provider(),
            'status' => strtoupper((string) ($response['response']['status'] ?? $response['status'] ?? 'PENDING')),
            'provider_refund_id' => $response['response']['transactionId']
                ?? $response['transactionId']
                ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string|string[]|null> $headers
     * @return array<string, mixed>
     */
    public function verifyWebhook(array $payload, array $headers, ?array $credentials = null): array
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new DomainException('Kashier webhook data is missing.');
        }

        $signature = $headers['x-kashier-signature']
            ?? $headers['X-Kashier-Signature']
            ?? null;

        if (is_array($signature)) {
            $signature = $signature[0] ?? null;
        }

        $this->verifier->verify(
            $data,
            is_string($signature) ? $signature : null,
            $this->webhookApiKey($credentials),
        );

        return [
            'provider' => $this->provider(),
            'event' => strtolower((string) ($payload['event'] ?? '')),
            'status' => strtoupper((string) ($data['status'] ?? '')),
            'transaction_id' => $data['transactionId'] ?? null,
            'kashier_order_id' => $data['orderId'] ?? null,
            'merchant_order_id' => $data['merchantOrderId'] ?? null,
            'order_reference' => $data['orderReference'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'data' => $data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveTransaction(string $reference, ?array $credentials = null): array
    {
        return $this->verifyPayment($this->client->getPaymentSessionPayment($reference, $credentials));
    }

    private function webhookApiKey(?array $credentials): string
    {
        $value = (string) ($credentials['payment_api_key'] ?? config('velora.payments.kashier.payment_api_key'));

        if ($value === '') {
            throw new DomainException('Kashier webhook API key is not configured.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    private function credentialsForContext(array $context): array
    {
        if (! array_key_exists('payment_account', $context)) {
            return [];
        }

        $credentials = $context['payment_account']['credentials'] ?? null;

        if (! is_array($credentials)) {
            throw new DomainException('Tenant payment account credentials are invalid.');
        }

        foreach ([
            'merchant_id',
            'secret_key',
            'payment_api_key',
            'merchant_redirect_url',
            'webhook_url',
        ] as $key) {
            if (trim((string) ($credentials[$key] ?? '')) === '') {
                throw new DomainException("Tenant Kashier credential [{$key}] is missing.");
            }
        }

        return $credentials;
    }

    private function minorToDecimal(int $amountMinor): string
    {
        $whole = intdiv($amountMinor, 100);
        $fraction = $amountMinor % 100;

        return $whole.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }
}
