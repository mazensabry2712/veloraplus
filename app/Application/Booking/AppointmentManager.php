<?php

namespace App\Application\Booking;

use App\Domain\Booking\AppointmentPaymentStatus;
use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\AppointmentStatusHistory;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AppointmentManager
{
    public function __construct(
        private readonly StaffAvailabilityManager $availability,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        Customer $customer,
        Staff $staff,
        Service $service,
        DateTimeInterface|string $startsAt,
        array $attributes = [],
    ): Appointment {
        $idempotencyKey = $this->normalizeIdempotencyKey($attributes['idempotency_key'] ?? null);

        if ($idempotencyKey !== null) {
            $existing = Appointment::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                $this->assertSameIdempotentRequest($existing, $customer, $staff, $service, $startsAt, $attributes);

                return $existing;
            }
        }

        try {
            return DB::transaction(function () use ($customer, $staff, $service, $startsAt, $attributes, $idempotencyKey): Appointment {
                $lockedStaff = Staff::query()
                    ->lockForUpdate()
                    ->find($staff->getKey());

                if ($lockedStaff === null) {
                    throw new DomainException('Staff member was not found in the current tenant.');
                }

                $staff = $lockedStaff;
                $this->ensureCreateable($customer, $staff, $service);

                if ($idempotencyKey !== null) {
                    $existing = Appointment::query()
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existing !== null) {
                        $this->assertSameIdempotentRequest($existing, $customer, $staff, $service, $startsAt, $attributes);

                        return $existing;
                    }
                }

                $range = $this->buildAppointmentRange($staff, $service, $startsAt);

                $this->assertAvailable($staff, $range['blocked_starts_at'], $range['blocked_ends_at']);

                $location = $this->resolveLocation($staff, $attributes['location_id'] ?? null);

                $appointment = Appointment::query()->create([
                    'customer_id' => $customer->getKey(),
                    'staff_id' => $staff->getKey(),
                    'location_id' => $location->getKey(),
                    'starts_at' => $range['starts_at'],
                    'ends_at' => $range['ends_at'],
                    'blocked_starts_at' => $range['blocked_starts_at'],
                    'blocked_ends_at' => $range['blocked_ends_at'],
                    'status' => AppointmentStatus::Confirmed,
                    'payment_status' => AppointmentPaymentStatus::Unpaid,
                    'idempotency_key' => $idempotencyKey,
                    'notes' => isset($attributes['notes']) ? trim((string) $attributes['notes']) : null,
                    'metadata' => $attributes['metadata'] ?? null,
                ]);

                AppointmentItem::query()->create([
                    'appointment_id' => $appointment->getKey(),
                    'service_id' => $service->getKey(),
                    'service_name' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                    'quantity' => 1,
                    'unit_price_minor' => $service->price_minor,
                    'currency' => $service->currency,
                    'line_total_minor' => $service->price_minor,
                    'metadata' => [
                        'buffer_before_minutes' => $service->buffer_before_minutes,
                        'buffer_after_minutes' => $service->buffer_after_minutes,
                    ],
                ]);

                $this->recordStatusChange(
                    $appointment,
                    from: null,
                    to: AppointmentStatus::Confirmed,
                    reason: 'Appointment created.',
                );

                return $appointment->load(['items', 'staff', 'customer', 'location', 'statusHistories']);
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ($idempotencyKey !== null) {
                $existing = Appointment::query()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing !== null) {
                    $this->assertSameIdempotentRequest($existing, $customer, $staff, $service, $startsAt, $attributes);

                    return $existing;
                }
            }

            throw $exception;
        }
    }

    public function reschedule(
        Appointment $appointment,
        DateTimeInterface|string $startsAt,
    ): Appointment {
        if (! in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true)) {
            throw new DomainException('Only pending or confirmed appointments can be rescheduled.');
        }

        return DB::transaction(function () use ($appointment, $startsAt): Appointment {
            $locked = Appointment::query()
                ->lockForUpdate()
                ->with(['staff', 'items'])
                ->find($appointment->getKey());

            if ($locked === null) {
                throw new DomainException('Appointment was not found in the current tenant.');
            }

            $staff = Staff::query()->lockForUpdate()->find($locked->staff_id);

            if ($staff === null) {
                throw new DomainException('Appointment staff member was not found.');
            }

            $service = $locked->items->first()?->service;

            if ($service === null) {
                throw new DomainException('Appointment service snapshot is incomplete.');
            }

            $range = $this->buildAppointmentRange($staff, $service, $startsAt);
            $this->assertAvailable($staff, $range['blocked_starts_at'], $range['blocked_ends_at'], $locked);

            $locked->update([
                'starts_at' => $range['starts_at'],
                'ends_at' => $range['ends_at'],
                'blocked_starts_at' => $range['blocked_starts_at'],
                'blocked_ends_at' => $range['blocked_ends_at'],
            ]);

            return $locked->refresh()->load(['items', 'staff', 'customer', 'location', 'statusHistories']);
        }, attempts: 3);
    }

    public function cancel(Appointment $appointment, ?string $reason = null): Appointment
    {
        return $this->transition(
            $appointment,
            AppointmentStatus::Cancelled,
            reason: $reason ?? 'Appointment cancelled.',
        );
    }

    public function confirm(Appointment $appointment): Appointment
    {
        return $this->transition($appointment, AppointmentStatus::Confirmed, 'Appointment confirmed.');
    }

    public function complete(Appointment $appointment): Appointment
    {
        return $this->transition($appointment, AppointmentStatus::Completed, 'Appointment completed.');
    }

    public function markNoShow(Appointment $appointment): Appointment
    {
        return $this->transition($appointment, AppointmentStatus::NoShow, 'Customer marked as no-show.');
    }

    private function transition(
        Appointment $appointment,
        AppointmentStatus $to,
        string $reason,
    ): Appointment {
        return DB::transaction(function () use ($appointment, $to, $reason): Appointment {
            $locked = Appointment::query()->lockForUpdate()->find($appointment->getKey());

            if ($locked === null) {
                throw new DomainException('Appointment was not found in the current tenant.');
            }

            $from = $locked->status;

            if ($from === $to) {
                return $locked->refresh();
            }

            $this->assertTransitionAllowed($from, $to);

            $locked->forceFill([
                'status' => $to,
                'cancellation_reason' => $to === AppointmentStatus::Cancelled ? $reason : null,
                'cancelled_at' => $to === AppointmentStatus::Cancelled ? now() : null,
            ])->save();

            $this->recordStatusChange($locked, $from, $to, $reason);

            return $locked->refresh()->load(['items', 'staff', 'customer', 'location', 'statusHistories']);
        }, attempts: 3);
    }

    private function assertTransitionAllowed(AppointmentStatus $from, AppointmentStatus $to): void
    {
        $allowed = match ($from) {
            AppointmentStatus::Pending => [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Cancelled,
            ],
            AppointmentStatus::Confirmed => [
                AppointmentStatus::Completed,
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
            ],
            AppointmentStatus::Completed,
            AppointmentStatus::Cancelled,
            AppointmentStatus::NoShow => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new DomainException(sprintf(
                'Appointment cannot transition from [%s] to [%s].',
                $from->value,
                $to->value,
            ));
        }
    }

    private function ensureCreateable(
        Customer $customer,
        Staff $staff,
        Service $service,
    ): void {
        if ($customer->trashed()) {
            throw new DomainException('Archived customers cannot receive appointments.');
        }

        if ($staff->trashed() || $staff->status !== 'active') {
            throw new DomainException('Only active staff can receive appointments.');
        }

        if ($service->trashed() || $service->status->value !== 'active') {
            throw new DomainException('Only active services can be booked.');
        }

        $assigned = DB::table('staff_services')
            ->where('staff_id', $staff->getKey())
            ->where('service_id', $service->getKey())
            ->exists();

        if (! $assigned) {
            throw new DomainException('The selected service is not assigned to this staff member.');
        }
    }

    /**
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, blocked_starts_at: CarbonImmutable, blocked_ends_at: CarbonImmutable}
     */
    private function buildAppointmentRange(
        Staff $staff,
        Service $service,
        DateTimeInterface|string $startsAt,
    ): array {
        $startsAt = $this->normalizeDateTime($startsAt);
        $endsAt = $startsAt->addMinutes($service->duration_minutes);
        $blockedStartsAt = $startsAt->subMinutes($service->buffer_before_minutes);
        $blockedEndsAt = $endsAt->addMinutes($service->buffer_after_minutes);

        $timezone = $staff->location?->timezone ?: config('app.timezone', 'UTC');
        $localStart = $startsAt->setTimezone($timezone);
        $localEnd = $endsAt->setTimezone($timezone);

        if ($localStart->toDateString() !== $localEnd->toDateString()) {
            throw new DomainException('Appointments cannot cross local calendar days in this slice.');
        }

        $windows = $this->availability->availabilityForDate($staff, $localStart);

        $fits = $windows->contains(
            fn (array $window): bool => $window['starts_at']->lessThanOrEqualTo($blockedStartsAt->setTimezone($timezone))
                && $window['ends_at']->greaterThanOrEqualTo($blockedEndsAt->setTimezone($timezone)),
        );

        if (! $fits) {
            throw new DomainException('Appointment time is outside the staff availability.');
        }

        return [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'blocked_starts_at' => $blockedStartsAt,
            'blocked_ends_at' => $blockedEndsAt,
        ];
    }

    private function assertAvailable(
        Staff $staff,
        CarbonImmutable $blockedStartsAt,
        CarbonImmutable $blockedEndsAt,
        ?Appointment $except = null,
    ): void {
        $query = Appointment::query()
            ->where('staff_id', $staff->getKey())
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
            ])
            ->where('blocked_starts_at', '<', $blockedEndsAt)
            ->where('blocked_ends_at', '>', $blockedStartsAt);

        if ($except !== null) {
            $query->whereKeyNot($except->getKey());
        }

        if ($query->exists()) {
            throw new DomainException('Staff member is already booked for the requested time.');
        }
    }

    private function resolveLocation(Staff $staff, mixed $locationId): Location
    {
        $location = $staff->location;

        if ($location === null) {
            throw new DomainException('Appointments require staff to be assigned to a location.');
        }

        if ($locationId !== null && (string) $location->getKey() !== (string) $locationId) {
            throw new DomainException('Appointment location must match the staff member location.');
        }

        return $location;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertSameIdempotentRequest(
        Appointment $existing,
        Customer $customer,
        Staff $staff,
        Service $service,
        DateTimeInterface|string $startsAt,
        array $attributes,
    ): void {
        $expectedStart = $this->normalizeDateTime($startsAt);

        if (
            (string) $existing->customer_id !== (string) $customer->getKey()
            || (string) $existing->staff_id !== (string) $staff->getKey()
            || (string) $existing->items()->value('service_id') !== (string) $service->getKey()
            || ! CarbonImmutable::parse($existing->getRawOriginal('starts_at'))->equalTo($expectedStart)
        ) {
            throw new DomainException('Idempotency key was already used for a different appointment request.');
        }
    }

    private function normalizeDateTime(DateTimeInterface|string $value): CarbonImmutable
    {
        return $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)->utc()
            : CarbonImmutable::parse($value)->utc();
    }

    private function normalizeIdempotencyKey(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (Str::length($value) > 190) {
            throw new DomainException('Idempotency key cannot exceed 190 characters.');
        }

        return $value;
    }

    private function recordStatusChange(
        Appointment $appointment,
        ?AppointmentStatus $from,
        AppointmentStatus $to,
        ?string $reason,
    ): void {
        AppointmentStatusHistory::query()->create([
            'appointment_id' => $appointment->getKey(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'reason' => $reason,
            'changed_at' => now(),
            'metadata' => null,
        ]);
    }
}
