<?php

namespace App\Application\Company;

use App\Models\Location;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LocationManager
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Location
    {
        return DB::transaction(function () use ($attributes): Location {
            return Location::query()->create($this->normalize($attributes));
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Location $location, array $attributes): Location
    {
        return DB::transaction(function () use ($location, $attributes): Location {
            $location->update($this->normalize(
                array_replace([
                    'name' => $location->name,
                    'code' => $location->code,
                    'address' => $location->address,
                    'country_code' => $location->country_code,
                    'city' => $location->city,
                    'timezone' => $location->timezone,
                    'status' => $location->status,
                    'metadata' => $location->metadata,
                ], $attributes),
                $location->getKey(),
            ));

            return $location->refresh();
        });
    }

    public function archive(Location $location): Location
    {
        DB::transaction(function () use ($location): void {
            $location->forceFill(['status' => 'inactive'])->save();
            $location->delete();
        });

        return $location->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes, ?string $ignoreLocationId = null): array
    {
        $name = trim((string) ($attributes['name'] ?? ''));

        if ($name === '') {
            throw new DomainException('Location name is required.');
        }

        $code = $attributes['code'] ?? null;

        if ($code !== null) {
            $code = trim((string) $code);
            $code = $code === '' ? null : Str::upper($code);

            if ($code !== null && mb_strlen($code) > 50) {
                throw new DomainException('Location code exceeds the allowed length.');
            }

            if ($code !== null && Location::withTrashed()
                ->where('code', $code)
                ->when($ignoreLocationId !== null, fn ($query) => $query->whereKey('!=', $ignoreLocationId))
                ->exists()) {
                throw new DomainException('Location code must be unique.');
            }
        }

        $countryCode = $attributes['country_code'] ?? null;

        if ($countryCode !== null) {
            $countryCode = trim((string) $countryCode);
            $countryCode = $countryCode === '' ? null : Str::upper($countryCode);
        }

        $timezone = trim((string) ($attributes['timezone'] ?? 'UTC'));

        if ($timezone === '') {
            throw new DomainException('Location timezone is required.');
        }

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new DomainException('Location timezone is invalid.');
        }

        $status = strtolower(trim((string) ($attributes['status'] ?? 'active')));

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new DomainException('Location status is invalid.');
        }

        return [
            'name' => $name,
            'code' => $code,
            'address' => $this->nullableString($attributes['address'] ?? null),
            'country_code' => $countryCode,
            'city' => $this->nullableString($attributes['city'] ?? null),
            'timezone' => $timezone,
            'status' => $status,
            'metadata' => $attributes['metadata'] ?? null,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
