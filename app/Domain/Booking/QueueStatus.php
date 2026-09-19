<?php

namespace App\Domain\Booking;

enum QueueStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
