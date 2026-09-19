<?php

namespace App\Application\Company;

use App\Models\Location;
use App\Models\Staff;
use DomainException;
use Illuminate\Support\Facades\DB;

final class StaffManager
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Staff
    {
        $data = $this->normalize($attributes);

        $this->assertLocationAllowed($data['location_id']);

        return DB::connection('tenant')->transaction(
            fn (): Staff => Staff::query()->create($data),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Staff $staff, array $attributes): Staff
    {
        $data = $this->normalize(array_replace([
            'account_id' => $staff->account_id,
            'location_id' => $staff->location_id,
            'name' => $staff->name,
            'phone' => $staff->phone,
            'email' => $staff->email,
            'status' => $staff->status,
            'metadata' => $staff->metadata,
        ], $attributes));

        $this->assertLocationAllowed($data['location_id']);

        return DB::connection('tenant')->transaction(function () use ($staff, $data): Staff {
            $staff->update($data);

            return $staff->refresh();
        });
    }

    public function archive(Staff $staff): Staff
    {
        DB::connection('tenant')->transaction(function () use ($staff): void {
            $staff->forceFill(['status' => 'inactive'])->save();
            $staff->delete();
        });

        return $staff->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        $name = trim((string) ($attributes['name'] ?? ''));

        if ($name === '') {
            throw new DomainException('Staff name is required.');
        }

        $status = strtolower(trim((string) ($attributes['status'] ?? 'active')));

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new DomainException('Staff status is invalid.');
        }

        $email = $attributes['email'] ?? null;

        if ($email !== null) {
            $email = trim((string) $email);
            $email = $email === '' ? null : strtolower($email);
        }

        $phone = $attributes['phone'] ?? null;

        if ($phone !== null) {
            $phone = trim((string) $phone);
            $phone = $phone === '' ? null : $phone;
        }

        $locationId = $attributes['location_id'] ?? null;

        return [
            'account_id' => array_key_exists('account_id', $attributes)
                ? $attributes['account_id']
                : null,
            'location_id' => $locationId,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'status' => $status,
            'metadata' => $attributes['metadata'] ?? null,
        ];
    }

    private function assertLocationAllowed(?string $locationId): void
    {
        if ($locationId === null) {
            return;
        }

        $location = Location::query()->find($locationId);

        if ($location === null || $location->status !== 'active') {
            throw new DomainException('Staff location must belong to the current tenant and be active.');
        }
    }
}
