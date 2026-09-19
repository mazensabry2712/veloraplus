<?php

namespace App\Domain\Billing\Contracts;

interface PaymentVerificationGateway
{
    /** @return array<string, mixed> */
    public function verifyPayment(array $payload): array;
}