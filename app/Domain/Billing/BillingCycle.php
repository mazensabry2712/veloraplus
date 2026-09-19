<?php

namespace App\Domain\Billing;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}