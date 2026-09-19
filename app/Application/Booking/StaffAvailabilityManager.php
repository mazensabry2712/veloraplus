<?php

namespace App\Application\Booking;

use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffBreak;
use App\Models\StaffService;
use App\Models\StaffTimeOff;
use App\Models\StaffWorkingHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class StaffAvailabilityManager
{
    public function assignService(Staff $staff, Service $service): StaffService
    {
        $this->ensureStaffUsable($staff);

        if ($service->trashed()) {
            throw new InvalidArgumentException('Archived services cannot be assigned to staff.');
        }

        return StaffService::query()->firstOrCreate([
            'staff_id' => $staff->getKey(),
            'service_id' => $service->getKey(),
        ]);
    }

    public function unassignService(Staff $staff, Service $service): void
    {
        StaffService::query()
            ->where('staff_id', $staff->getKey())
            ->where('service_id', $service->getKey())
            ->delete();
    }

    public function saveWorkingHour(
        Staff $staff,
        int $dayOfWeek,
        string $startsAt,
        string $endsAt,
        ?StaffWorkingHour $workingHour = null,
    ): StaffWorkingHour {
        $this->ensureStaffUsable($staff);

        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            throw new InvalidArgumentException('Day of week must be between 0 and 6.');
        }

        [$startsAt, $endsAt] = $this->normalizeTimeRange($startsAt, $endsAt);

        if ($workingHour !== null && (string) $workingHour->staff_id !== (string) $staff->getKey()) {
            throw new InvalidArgumentException('Working hour does not belong to the selected staff member.');
        }

        $query = StaffWorkingHour::query()
            ->where('staff_id', $staff->getKey())
            ->where('day_of_week', $dayOfWeek)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($workingHour !== null) {
            $query->whereKeyNot($workingHour->getKey());
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('Working hours cannot overlap for the same day.');
        }

        $workingHour ??= new StaffWorkingHour;

        $workingHour->fill([
            'staff_id' => $staff->getKey(),
            'day_of_week' => $dayOfWeek,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $workingHour->save();

        return $workingHour;
    }

    public function deleteWorkingHour(StaffWorkingHour $workingHour): void
    {
        $workingHour->delete();
    }

    public function saveBreak(
        StaffWorkingHour $workingHour,
        string $startsAt,
        string $endsAt,
        ?string $label = null,
        ?StaffBreak $break = null,
    ): StaffBreak {
        $staff = $workingHour->relationLoaded('staff')
            ? $workingHour->staff
            : $workingHour->load('staff')->staff;

        if ($staff === null) {
            throw new InvalidArgumentException('Working hour staff member was not found.');
        }

        $this->ensureStaffUsable($staff);

        [$startsAt, $endsAt] = $this->normalizeTimeRange($startsAt, $endsAt);

        if ($break !== null && (string) $break->staff_working_hour_id !== (string) $workingHour->getKey()) {
            throw new InvalidArgumentException('Break does not belong to the selected working hour.');
        }

        if ($startsAt < $workingHour->starts_at || $endsAt > $workingHour->ends_at) {
            throw new InvalidArgumentException('Break must be fully contained inside its working hours.');
        }

        $query = StaffBreak::query()
            ->where('staff_working_hour_id', $workingHour->getKey())
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($break !== null) {
            $query->whereKeyNot($break->getKey());
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('Breaks cannot overlap for the same working hour.');
        }

        $break ??= new StaffBreak;

        $break->fill([
            'staff_working_hour_id' => $workingHour->getKey(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'label' => $label,
        ]);
        $break->save();

        return $break;
    }

    public function deleteBreak(StaffBreak $break): void
    {
        $break->delete();
    }

    public function saveTimeOff(
        Staff $staff,
        DateTimeInterface|string $startsAt,
        DateTimeInterface|string $endsAt,
        ?string $reason = null,
        ?StaffTimeOff $timeOff = null,
    ): StaffTimeOff {
        $this->ensureStaffUsable($staff);

        $startsAt = $this->normalizeDateTime($startsAt);
        $endsAt = $this->normalizeDateTime($endsAt);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException('Time off end must be after its start.');
        }

        if ($timeOff !== null && (string) $timeOff->staff_id !== (string) $staff->getKey()) {
            throw new InvalidArgumentException('Time off does not belong to the selected staff member.');
        }

        $query = StaffTimeOff::query()
            ->where('staff_id', $staff->getKey())
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($timeOff !== null) {
            $query->whereKeyNot($timeOff->getKey());
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('Time off intervals cannot overlap.');
        }

        $timeOff ??= new StaffTimeOff;

        $timeOff->fill([
            'staff_id' => $staff->getKey(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => $reason,
        ]);
        $timeOff->save();

        return $timeOff;
    }

    public function deleteTimeOff(StaffTimeOff $timeOff): void
    {
        $timeOff->delete();
    }

    /**
     * Return local-time availability windows for a calendar date before appointments are considered.
     *
     * Each item contains CarbonImmutable starts_at and ends_at in the staff location timezone.
     *
     * @return Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function availabilityForDate(Staff $staff, CarbonInterface|string $date): Collection
    {
        $this->ensureStaffUsable($staff);

        $timezone = $this->staffTimezone($staff);
        $localDate = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->setTimezone($timezone)
            : CarbonImmutable::parse($date, $timezone);

        $hours = StaffWorkingHour::query()
            ->with('breaks')
            ->where('staff_id', $staff->getKey())
            ->where('day_of_week', $localDate->dayOfWeek)
            ->orderBy('starts_at')
            ->get();

        $dayStartUtc = $localDate->startOfDay()->utc();
        $dayEndUtc = $localDate->endOfDay()->utc();

        $timeOffs = StaffTimeOff::query()
            ->where('staff_id', $staff->getKey())
            ->where('starts_at', '<', $dayEndUtc)
            ->where('ends_at', '>', $dayStartUtc)
            ->get();

        $windows = collect();

        foreach ($hours as $hour) {
            $segments = [[
                CarbonImmutable::parse($localDate->format('Y-m-d').' '.$hour->starts_at, $timezone),
                CarbonImmutable::parse($localDate->format('Y-m-d').' '.$hour->ends_at, $timezone),
            ]];

            foreach ($hour->breaks as $break) {
                $segments = $this->subtractLocalInterval(
                    $segments,
                    CarbonImmutable::parse($localDate->format('Y-m-d').' '.$break->starts_at, $timezone),
                    CarbonImmutable::parse($localDate->format('Y-m-d').' '.$break->ends_at, $timezone),
                );
            }

            foreach ($timeOffs as $timeOff) {
                $offStart = CarbonImmutable::parse($timeOff->getRawOriginal('starts_at'), 'UTC')->setTimezone($timezone);
                $offEnd = CarbonImmutable::parse($timeOff->getRawOriginal('ends_at'), 'UTC')->setTimezone($timezone);

                $segments = $this->subtractLocalInterval($segments, $offStart, $offEnd);
            }

            foreach ($segments as [$startsAt, $endsAt]) {
                if ($startsAt->lessThan($endsAt)) {
                    $windows->push([
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                    ]);
                }
            }
        }

        return $windows->values();
    }

    private function ensureStaffUsable(Staff $staff): void
    {
        if ($staff->trashed()) {
            throw new InvalidArgumentException('Archived staff members cannot have availability configured.');
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalizeTimeRange(string $startsAt, string $endsAt): array
    {
        $startsAt = $this->normalizeTime($startsAt);
        $endsAt = $this->normalizeTime($endsAt);

        if ($startsAt >= $endsAt) {
            throw new InvalidArgumentException('End time must be after start time. Overnight intervals are not supported yet.');
        }

        return [$startsAt, $endsAt];
    }

    private function normalizeTime(string $time): string
    {
        foreach (['H:i', 'H:i:s'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!'.$format, $time);

                if ($parsed !== false && $parsed->format($format) === $time) {
                    return $parsed->format('H:i:s');
                }
            } catch (InvalidArgumentException) {
                //
            }
        }

        throw new InvalidArgumentException('Time must use HH:MM or HH:MM:SS format.');
    }

    private function normalizeDateTime(DateTimeInterface|string $value): CarbonImmutable
    {
        return $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)->utc()
            : CarbonImmutable::parse($value)->utc();
    }

    private function staffTimezone(Staff $staff): string
    {
        $location = $staff->relationLoaded('location')
            ? $staff->location
            : $staff->load('location')->location;

        return $location?->timezone ?: config('app.timezone', 'UTC');
    }

    /**
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $segments
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function subtractLocalInterval(
        array $segments,
        CarbonImmutable $cutStart,
        CarbonImmutable $cutEnd,
    ): array {
        if ($cutEnd->lessThanOrEqualTo($cutStart)) {
            return $segments;
        }

        $result = [];

        foreach ($segments as [$segmentStart, $segmentEnd]) {
            if ($cutEnd->lessThanOrEqualTo($segmentStart) || $cutStart->greaterThanOrEqualTo($segmentEnd)) {
                $result[] = [$segmentStart, $segmentEnd];

                continue;
            }

            if ($cutStart->greaterThan($segmentStart)) {
                $result[] = [$segmentStart, $cutStart->min($segmentEnd)];
            }

            if ($cutEnd->lessThan($segmentEnd)) {
                $result[] = [$cutEnd->max($segmentStart), $segmentEnd];
            }
        }

        return $result;
    }
}
