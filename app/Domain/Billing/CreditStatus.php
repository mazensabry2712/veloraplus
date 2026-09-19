<?php

namespace App\Domain\Billing;

enum CreditStatus: string
{
    case Active = 'active';
    case Consumed = 'consumed';
    case Expired = 'expired';
    case Void = 'void';
}
