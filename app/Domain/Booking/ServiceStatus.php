<?php

namespace App\Domain\Booking;

enum ServiceStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
