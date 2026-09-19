<?php

namespace App\Domain\Payments\Contracts;

interface PlatformPaymentGateway
{
    public function provider(): string;
}
