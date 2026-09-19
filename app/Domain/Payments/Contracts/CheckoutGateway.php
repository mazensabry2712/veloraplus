<?php

namespace App\Domain\Payments\Contracts;

interface CheckoutGateway
{
    /** @return array<string, mixed> */
    public function createCheckout(array $context): array;
}
