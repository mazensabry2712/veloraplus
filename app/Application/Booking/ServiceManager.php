<?php

namespace App\Application\Booking;

use App\Domain\Booking\ServiceStatus;
use App\Models\Service;
use DomainException;

final class ServiceManager
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Service
    {
        return Service::query()->create($this->normalize($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Service $service, array $attributes): Service
    {
        $current = [
            'name' => $service->name,
            'slug' => $service->slug,
            'description' => $service->description,
            'duration_minutes' => $service->duration_minutes,
            'buffer_before_minutes' => $service->buffer_before_minutes,
            'buffer_after_minutes' => $service->buffer_after_minutes,
            'price_minor' => $service->price_minor,
            'currency' => $service->currency,
            'deposit_amount_minor' => $service->deposit_amount_minor,
            'status' => $service->status instanceof ServiceStatus ? $service->status->value : (string) $service->status,
            'online_bookable' => $service->online_bookable,
            'capacity' => $service->capacity,
            'metadata' => $service->metadata,
        ];

        $service->update($this->normalize(array_replace($current, $attributes)));

        return $service->refresh();
    }

    public function archive(Service $service): Service
    {
        $service->forceFill([
            'status' => ServiceStatus::Inactive,
        ])->save();

        $service->delete();

        return $service->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        $name = trim((string) ($attributes['name'] ?? ''));

        if ($name === '') {
            throw new DomainException('Service name is required.');
        }

        $slug = $this->resolveUniqueSlug(
            isset($attributes['slug']) ? (string) $attributes['slug'] : $name,
        );

        $duration = (int) ($attributes['duration_minutes'] ?? 0);
        $bufferBefore = (int) ($attributes['buffer_before_minutes'] ?? 0);
        $bufferAfter = (int) ($attributes['buffer_after_minutes'] ?? 0);
        $priceMinor = (int) ($attributes['price_minor'] ?? 0);
        $depositMinor = (int) ($attributes['deposit_amount_minor'] ?? 0);
        $capacity = (int) ($attributes['capacity'] ?? 0);

        if ($duration < 1) {
            throw new DomainException('Service duration must be greater than zero.');
        }

        if ($bufferBefore < 0 || $bufferAfter < 0) {
            throw new DomainException('Service buffers cannot be negative.');
        }

        if ($priceMinor < 0) {
            throw new DomainException('Service price cannot be negative.');
        }

        if ($depositMinor < 0 || $depositMinor > $priceMinor) {
            throw new DomainException('Service deposit must be between zero and the service price.');
        }

        if ($capacity < 1) {
            throw new DomainException('Service capacity must be greater than zero.');
        }

        $currency = strtoupper(trim((string) ($attributes['currency'] ?? '')));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainException('Service currency must be a valid ISO 4217 three-letter code.');
        }

        $status = ServiceStatus::tryFrom(
            strtolower(trim((string) ($attributes['status'] ?? ServiceStatus::Active->value))),
        );

        if ($status === null) {
            throw new DomainException('Invalid service status.');
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => isset($attributes['description']) ? trim((string) $attributes['description']) : null,
            'duration_minutes' => $duration,
            'buffer_before_minutes' => $bufferBefore,
            'buffer_after_minutes' => $bufferAfter,
            'price_minor' => $priceMinor,
            'currency' => $currency,
            'deposit_amount_minor' => $depositMinor,
            'status' => $status,
            'online_bookable' => (bool) ($attributes['online_bookable'] ?? true),
            'capacity' => $capacity,
            'metadata' => $attributes['metadata'] ?? null,
        ];
    }

    private function resolveUniqueSlug(string $value): string
    {
        $base = \Illuminate\Support\Str::slug(trim($value));

        if ($base === '') {
            $base = 'service';
        }

        $slug = $base;
        $suffix = 2;

        while (Service::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
