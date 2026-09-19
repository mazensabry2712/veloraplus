<?php

namespace App\Domain\Billing\Contracts;

interface CheckoutGateway
{
    /** @return array<string, mixed> */
    public function createCheckout(array $payload): array;
}