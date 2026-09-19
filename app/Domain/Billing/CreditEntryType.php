<?php

namespace App\Domain\Billing;

enum CreditEntryType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}