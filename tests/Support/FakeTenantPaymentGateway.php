<?php

namespace Tests\Support;

use App\Domain\Payments\Contracts\CheckoutGateway;
use App\Domain\Payments\Contracts\TenantPaymentGateway;
use RuntimeException;

final class FakeTenantPaymentGateway implements TenantPaymentGateway, CheckoutGateway
{
    public static array $calls = [];
    public static bool $shouldFail = false;

    public function provider(): string
    {
        return 'fake';
    }

    public function createCheckout(array $context): array
    {
        self::$calls[] = $context;

        if (self::$shouldFail) {
            throw new RuntimeException('provider down');
        }

        $number = count(self::$calls);

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

    public static function reset(): void
    {
        self::$calls = [];
        self::$shouldFail = false;
    }
}
