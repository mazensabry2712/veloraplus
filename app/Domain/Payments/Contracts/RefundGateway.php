<?php

namespace App\Domain\Payments\Contracts;

interface RefundGateway
{
    /** @return array<string, mixed> */
    public function createRefund(array $context): array;
}
