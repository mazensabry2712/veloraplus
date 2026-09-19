<?php

namespace App\Application\Booking;

use App\Domain\Booking\QueueEntryStatus;
use App\Domain\Booking\QueueStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\Service;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class QueueManager
{
    public function createQueue(
        Location $location,
        Service $service,
        ?string $businessDate = null,
        array $metadata = [],
    ): Queue {
        $this->assertLocationActive($location);
        $this->assertServiceBookable($service);

        $date = $businessDate !== null
            ? CarbonImmutable::parse($businessDate, $this->locationTimezone($location))->toDateString()
            : CarbonImmutable::now($this->locationTimezone($location))->toDateString();

        $attributes = [
            'location_id' => $location->getKey(),
            'service_id' => $service->getKey(),
            'business_date' => $date,
        ];

        $existing = Queue::query()->where($attributes)->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return Queue::query()->create($attributes + [
                'status' => QueueStatus::Open,
                'next_position' => 1,
                'metadata' => $metadata,
            ]);
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['19', '23000', '23505'], true)) {
                throw $exception;
            }

            return Queue::query()
                ->where($attributes)
                ->firstOrFail();
        }
    }

    public function open(Queue $queue): Queue
    {
        return DB::transaction(function () use ($queue): Queue {
            $locked = $this->lockQueue($queue);

            $this->assertQueueServiceAndLocation($locked);

            $locked->update(['status' => QueueStatus::Open]);

            return $locked->refresh();
        });
    }

    public function close(Queue $queue): Queue
    {
        return DB::transaction(function () use ($queue): Queue {
            $locked = $this->lockQueue($queue);

            $serving = $locked->entries()
                ->where('status', QueueEntryStatus::Serving->value)
                ->exists();

            if ($serving) {
                throw new DomainException('A queue with a serving entry cannot be closed.');
            }

            $locked->update(['status' => QueueStatus::Closed]);

            return $locked->refresh();
        });
    }

    public function enqueue(
        Queue $queue,
        Customer $customer,
        ?Appointment $appointment = null,
        ?string $idempotencyKey = null,
        ?string $notes = null,
        array $metadata = [],
    ): QueueEntry {
        $idempotencyKey = $idempotencyKey !== null ? trim($idempotencyKey) : null;

        return DB::transaction(function () use (
            $queue,
            $customer,
            $appointment,
            $idempotencyKey,
            $notes,
            $metadata,
        ): QueueEntry {
            $lockedQueue = $this->lockQueue($queue);

            $this->assertQueueServiceAndLocation($lockedQueue);

            if ($lockedQueue->status !== QueueStatus::Open) {
                throw new DomainException('The queue is closed.');
            }

            $freshCustomer = Customer::query()->find($customer->getKey());

            if ($freshCustomer === null || $freshCustomer->trashed() || $freshCustomer->status !== 'active') {
                throw new DomainException('Only active customers can join a queue.');
            }

            if ($idempotencyKey !== null) {
                $existing = QueueEntry::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ((string) $existing->queue_id !== (string) $lockedQueue->getKey()) {
                        throw new DomainException('Queue entry idempotency key was already used for another queue.');
                    }

                    return $existing;
                }
            }

            if ($appointment !== null) {
                $this->assertAppointmentMatchesQueue($appointment, $lockedQueue, $freshCustomer);

                $alreadyQueued = QueueEntry::query()
                    ->where('queue_id', $lockedQueue->getKey())
                    ->where('appointment_id', $appointment->getKey())
                    ->whereIn('status', [
                        QueueEntryStatus::Waiting->value,
                        QueueEntryStatus::Serving->value,
                    ])
                    ->lockForUpdate()
                    ->first();

                if ($alreadyQueued !== null) {
                    return $alreadyQueued;
                }
            }

            $position = $lockedQueue->next_position;

            if ($position < 1) {
                throw new DomainException('Queue position sequence is invalid.');
            }

            $lockedQueue->update([
                'next_position' => $position + 1,
            ]);

            return QueueEntry::query()->create([
                'queue_id' => $lockedQueue->getKey(),
                'customer_id' => $freshCustomer->getKey(),
                'appointment_id' => $appointment?->getKey(),
                'position' => $position,
                'status' => QueueEntryStatus::Waiting,
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                'joined_at' => now(),
                'notes' => $notes,
                'metadata' => $metadata,
            ]);
        });
    }

    public function callNext(Queue $queue): ?QueueEntry
    {
        return DB::transaction(function () use ($queue): ?QueueEntry {
            $lockedQueue = $this->lockQueue($queue);

            if ($lockedQueue->status !== QueueStatus::Open) {
                throw new DomainException('The queue is closed.');
            }

            if ($lockedQueue->entries()->where('status', QueueEntryStatus::Serving->value)->exists()) {
                throw new DomainException('The queue already has a serving entry.');
            }

            $next = $lockedQueue->entries()
                ->where('status', QueueEntryStatus::Waiting->value)
                ->orderBy('position')
                ->lockForUpdate()
                ->first();

            if ($next === null) {
                return null;
            }

            $next->update([
                'status' => QueueEntryStatus::Serving,
                'called_at' => now(),
            ]);

            return $next->refresh();
        });
    }

    public function complete(QueueEntry $entry): QueueEntry
    {
        return DB::transaction(function () use ($entry): QueueEntry {
            $locked = $this->lockEntryWithQueue($entry);

            if ($locked->status !== QueueEntryStatus::Serving) {
                throw new DomainException('Only a serving queue entry can be completed.');
            }

            $locked->update([
                'status' => QueueEntryStatus::Completed,
                'completed_at' => now(),
            ]);

            return $locked->refresh();
        });
    }

    public function skip(QueueEntry $entry, ?string $reason = null): QueueEntry
    {
        return DB::transaction(function () use ($entry, $reason): QueueEntry {
            $locked = $this->lockEntryWithQueue($entry);

            if (! in_array($locked->status, [
                QueueEntryStatus::Waiting,
                QueueEntryStatus::Serving,
            ], true)) {
                throw new DomainException('Only waiting or serving queue entries can be skipped.');
            }

            $locked->update([
                'status' => QueueEntryStatus::Skipped,
                'skipped_at' => now(),
                'metadata' => array_merge($locked->metadata ?? [], [
                    'skip_reason' => $reason,
                ]),
            ]);

            return $locked->refresh();
        });
    }

    public function markNoShow(QueueEntry $entry, ?string $reason = null): QueueEntry
    {
        return DB::transaction(function () use ($entry, $reason): QueueEntry {
            $locked = $this->lockEntryWithQueue($entry);

            if (! in_array($locked->status, [
                QueueEntryStatus::Waiting,
                QueueEntryStatus::Serving,
            ], true)) {
                throw new DomainException('Only waiting or serving queue entries can be marked no-show.');
            }

            $locked->update([
                'status' => QueueEntryStatus::NoShow,
                'no_show_at' => now(),
                'metadata' => array_merge($locked->metadata ?? [], [
                    'no_show_reason' => $reason,
                ]),
            ]);

            return $locked->refresh();
        });
    }

    private function assertAppointmentMatchesQueue(
        Appointment $appointment,
        Queue $queue,
        Customer $customer,
    ): void {
        $freshAppointment = Appointment::query()
            ->with(['items'])
            ->find($appointment->getKey());

        if ($freshAppointment === null) {
            throw new DomainException('Appointment was not found in the current tenant.');
        }

        if ($freshAppointment->customer_id !== $customer->getKey()) {
            throw new DomainException('Queue customer must match the appointment customer.');
        }

        if ($freshAppointment->location_id !== $queue->location_id) {
            throw new DomainException('Queue location must match the appointment location.');
        }

        if (in_array($freshAppointment->status?->value, ['cancelled', 'no_show'], true)) {
            throw new DomainException('Cancelled or no-show appointments cannot join a queue.');
        }

        $item = $freshAppointment->items->first();

        if ($item === null || $item->service_id !== $queue->service_id) {
            throw new DomainException('Queue service must match the appointment service.');
        }

        $queueDate = CarbonImmutable::parse($queue->business_date, $this->locationTimezone($queue->location))->toDateString();
        $appointmentDate = CarbonImmutable::instance($freshAppointment->starts_at)
            ->timezone($this->locationTimezone($queue->location))
            ->toDateString();

        if ($queueDate !== $appointmentDate) {
            throw new DomainException('Appointment date does not match the queue business date.');
        }
    }

    private function lockQueue(Queue $queue): Queue
    {
        $locked = Queue::query()
            ->lockForUpdate()
            ->find($queue->getKey());

        if ($locked === null) {
            throw new DomainException('Queue was not found in the current tenant.');
        }

        return $locked;
    }

    private function lockEntryWithQueue(QueueEntry $entry): QueueEntry
    {
        $lockedEntry = QueueEntry::query()
            ->lockForUpdate()
            ->find($entry->getKey());

        if ($lockedEntry === null) {
            throw new DomainException('Queue entry was not found in the current tenant.');
        }

        $lockedQueue = $this->lockQueue($lockedEntry->queue);

        if ((string) $lockedQueue->getKey() !== (string) $lockedEntry->queue_id) {
            throw new DomainException('Queue entry queue relationship is invalid.');
        }

        return $lockedEntry;
    }

    private function assertQueueServiceAndLocation(Queue $queue): void
    {
        $this->assertLocationActive($queue->location);
        $this->assertServiceBookable($queue->service);
    }

    private function assertLocationActive(Location $location): void
    {
        if ($location->status !== 'active') {
            throw new DomainException('Queue location must be active.');
        }
    }

    private function assertServiceBookable(Service $service): void
    {
        if ($service->trashed() || $service->status?->value !== 'active') {
            throw new DomainException('Queue service must be active.');
        }
    }

    private function locationTimezone(Location $location): string
    {
        return (string) ($location->timezone ?: config('app.timezone', 'UTC'));
    }
}
