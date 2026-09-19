<?php

namespace Database\Factories;

use App\Domain\Booking\AppointmentPaymentStatus;
use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $startsAt = now()->addDay()->setTime(10, 0);

        return [
            'customer_id' => Customer::factory(),
            'staff_id' => Staff::factory(),
            'location_id' => Location::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'blocked_starts_at' => $startsAt,
            'blocked_ends_at' => $startsAt->copy()->addHour(),
            'status' => AppointmentStatus::Confirmed,
            'payment_status' => AppointmentPaymentStatus::Unpaid,
            'idempotency_key' => null,
            'cancellation_reason' => null,
            'cancelled_at' => null,
            'notes' => null,
            'metadata' => [],
        ];
    }
}
