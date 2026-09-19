<?php

namespace Database\Factories;

use App\Domain\Booking\QueueStatus;
use App\Models\Location;
use App\Models\Queue;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Queue> */
class QueueFactory extends Factory
{
    protected $model = Queue::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'service_id' => Service::factory(),
            'business_date' => now()->toDateString(),
            'status' => QueueStatus::Open,
            'next_position' => 1,
            'metadata' => [],
        ];
    }
}
