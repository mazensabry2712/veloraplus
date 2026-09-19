<?php

namespace App\Domain\Booking;

enum QueueEntryStatus: string
{
    case Waiting = 'waiting';
    case Serving = 'serving';
    case Completed = 'completed';
    case Skipped = 'skipped';
    case NoShow = 'no_show';
}
