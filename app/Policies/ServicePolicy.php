<?php

namespace App\Policies;

use App\Models\PlatformAccount;
use App\Models\Service;

class ServicePolicy
{
    public function viewAny(PlatformAccount $user): bool
    {
        return $user->can('booking.services.view');
    }

    public function view(PlatformAccount $user, Service $service): bool
    {
        return $user->can('booking.services.view');
    }

    public function create(PlatformAccount $user): bool
    {
        return $user->can('booking.services.manage');
    }

    public function update(PlatformAccount $user, Service $service): bool
    {
        return $user->can('booking.services.manage');
    }

    public function delete(PlatformAccount $user, Service $service): bool
    {
        return $user->can('booking.services.manage');
    }
}
