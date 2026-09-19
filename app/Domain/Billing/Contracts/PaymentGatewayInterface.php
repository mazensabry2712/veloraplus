<?php

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\PaymentGatewayCapabilities;

interface PaymentGatewayInterface
{
    public function key(): string;

    public function capabilities(): PaymentGatewayCapabilities;
}