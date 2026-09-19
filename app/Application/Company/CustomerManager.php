<?php

namespace App\Application\Company;

use App\Models\Customer;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CustomerManager
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Customer
    {
        return DB::connection('tenant')->transaction(
            fn (): Customer => Customer::query()->create($this->normalize($attributes)),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Customer $customer, array $attributes): Customer
    {
        $data = $this->normalize(array_replace([
            'customer_account_id' => $customer->customer_account_id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'status' => $customer->status,
            'source' => $customer->source,
            'notes' => $customer->notes,
            'metadata' => $customer->metadata,
        ], $attributes));

        return DB::connection('tenant')->transaction(function () use ($customer, $data): Customer {
            $customer->update($data);

            return $customer->refresh();
        });
    }

    public function archive(Customer $customer): Customer
    {
        DB::connection('tenant')->transaction(function () use ($customer): void {
            $customer->forceFill(['status' => 'inactive'])->save();
            $customer->delete();
        });

        return $customer->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        $name = trim((string) ($attributes['name'] ?? ''));

        if ($name === '') {
            throw new DomainException('Customer name is required.');
        }

        $status = strtolower(trim((string) ($attributes['status'] ?? 'active')));

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new DomainException('Customer status is invalid.');
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

        $source = $attributes['source'] ?? null;

        if ($source !== null) {
            $source = trim((string) $source);
            $source = $source === '' ? null : $source;
        }

        $notes = $attributes['notes'] ?? null;

        if ($notes !== null) {
            $notes = trim((string) $notes);
            $notes = $notes === '' ? null : $notes;
        }

        return [
            'customer_account_id' => array_key_exists('customer_account_id', $attributes)
                ? $attributes['customer_account_id']
                : null,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'status' => $status,
            'source' => $source,
            'notes' => $notes,
            'metadata' => $attributes['metadata'] ?? null,
        ];
    }
}
