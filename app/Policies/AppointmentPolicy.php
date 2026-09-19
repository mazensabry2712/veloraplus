<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\PlatformAccount;

class AppointmentPolicy
{
    public function viewAny(PlatformAccount $account): bool
    {
        return $account->can('booking.appointments.view');
    }

    public function view(PlatformAccount $account, Appointment $appointment): bool
    {
        return $account->can('booking.appointments.view');
    }

    public function create(PlatformAccount $account): bool
    {
        return $account->can('booking.appointments.manage');
    }

    public function update(PlatformAccount $account, Appointment $appointment): bool
    {
        return $account->can('booking.appointments.manage');
    }

    public function delete(PlatformAccount $account, Appointment $appointment): bool
    {
        return $account->can('booking.appointments.manage');
    }

    public function manage(PlatformAccount $account, Appointment $appointment): bool
    {
        return $account->can('booking.appointments.manage');
    }
}
