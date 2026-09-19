<?php

namespace App\Domain\Payments\Contracts;

interface PaymentVerificationGateway
{
    /** @return array<string, mixed> */
    public function verifyPayment(array $payload): array;
}
