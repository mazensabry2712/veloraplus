<?php

namespace App\Policies;

use App\Models\PlatformAccount;
use App\Models\Queue;
use App\Models\QueueEntry;

class QueuePolicy
{
    public function viewAny(PlatformAccount $account): bool
    {
        return $account->can('booking.queues.view');
    }

    public function view(PlatformAccount $account, Queue $queue): bool
    {
        return $account->can('booking.queues.view');
    }

    public function create(PlatformAccount $account): bool
    {
        return $account->can('booking.queues.manage');
    }

    public function manage(PlatformAccount $account, Queue $queue): bool
    {
        return $account->can('booking.queues.manage');
    }

    public function manageEntry(PlatformAccount $account, QueueEntry $entry): bool
    {
        return $account->can('booking.queues.manage');
    }
}
