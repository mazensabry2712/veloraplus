<?php

namespace App\Domain\Billing;

enum PaymentGatewayContext: string
{
    case Platform = 'platform';
    case Tenant = 'tenant';
}