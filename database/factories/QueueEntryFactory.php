<?php

namespace Database\Factories;

use App\Domain\Booking\QueueEntryStatus;
use App\Models\Customer;
use App\Models\Queue;
use App\Models\QueueEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QueueEntry> */
class QueueEntryFactory extends Factory
{
    protected $model = QueueEntry::class;

    public function definition(): array
    {
        return [
            'queue_id' => Queue::factory(),
            'customer_id' => Customer::factory(),
            'appointment_id' => null,
            'position' => 1,
            'status' => QueueEntryStatus::Waiting,
            'idempotency_key' => null,
            'joined_at' => now(),
            'called_at' => null,
            'completed_at' => null,
            'skipped_at' => null,
            'no_show_at' => null,
            'notes' => null,
            'metadata' => [],
        ];
    }
}
