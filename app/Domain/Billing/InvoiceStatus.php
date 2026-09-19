<?php

namespace App\Domain\Billing;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Paid = 'paid';
    case PastDue = 'past_due';
    case Void = 'void';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
}