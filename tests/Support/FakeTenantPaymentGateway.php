<?php

namespace Tests\Support;

use App\Domain\Payments\Contracts\CheckoutGateway;
use App\Domain\Payments\Contracts\RefundGateway;
use App\Domain\Payments\Contracts\TenantPaymentGateway;
use App\Domain\Payments\Contracts\TransactionLookupGateway;
use RuntimeException;

final class FakeTenantPaymentGateway implements
    TenantPaymentGateway,
    CheckoutGateway,
    TransactionLookupGateway,
    RefundGateway
{
    public static array $calls = [];
    public static bool $shouldFail = false;
    public static string $transactionStatus = 'PENDING';
    public static string $transactionAmount = '0.00';
    public static string $transactionCurrency = 'EGP';
    public static string $transactionId = 'TX-FAKE';
    public static string $transactionOrderId = 'ORDER-FAKE';
    public static string $refundStatus = 'SUCCESS';
    public static string $refundId = 'REFUND-FAKE';

    public function provider(): string
    {
        return 'fake';
    }

    public function createCheckout(array $context): array
    {
        self::$calls[] = [
            'type' => 'checkout',
            ...$context,
        ];

        if (self::$shouldFail) {
            throw new RuntimeException('provider down');
        }

        $number = count(array_filter(
            self::$calls,
            fn (array $call): bool => ($call['type'] ?? null) === 'checkout',
        ));

        return [
            'provider' => 'fake',
            'session_id' => 'SESSION-'.$number,
            'checkout_url' => 'https://pay.example.test/session/'.$number,
            'merchant_order_id' => $context['merchant_order_id'],
            'provider_payment_id' => 'PAY-'.$number,
            'provider_order_id' => 'ORDER-'.$number,
            'status' => 'CREATED',
        ];
    }

    public function retrieveTransaction(string $reference, ?array $credentials = null): array
    {
        self::$calls[] = [
            'type' => 'lookup',
            'reference' => $reference,
            'credentials' => $credentials,
        ];

        return [
            'provider' => 'fake',
            'status' => self::$transactionStatus,
            'transaction_id' => self::$transactionId,
            'order_id' => self::$transactionOrderId,
            'merchant_order_id' => null,
            'amount' => self::$transactionAmount,
            'currency' => self::$transactionCurrency,
        ];
    }

    public function createRefund(array $context): array
    {
        self::$calls[] = [
            'type' => 'refund',
            ...$context,
        ];

        if (self::$shouldFail) {
            throw new RuntimeException('refund provider down');
        }

        return [
            'provider' => 'fake',
            'status' => self::$refundStatus,
            'provider_refund_id' => self::$refundId,
        ];
    }

    public static function reset(): void
    {
        self::$calls = [];
        self::$shouldFail = false;
        self::$transactionStatus = 'PENDING';
        self::$transactionAmount = '0.00';
        self::$transactionCurrency = 'EGP';
        self::$transactionId = 'TX-FAKE';
        self::$transactionOrderId = 'ORDER-FAKE';
        self::$refundStatus = 'SUCCESS';
        self::$refundId = 'REFUND-FAKE';
    }
}
