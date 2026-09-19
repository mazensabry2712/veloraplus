<?php

namespace App\Domain\Payments\Contracts;

interface TenantPaymentGateway
{
    public function provider(): string;
}
