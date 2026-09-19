<?php

namespace App\Domain\Billing\Contracts;

interface RefundGateway
{
    /** @return array<string, mixed> */
    public function refund(array $payload): array;
}