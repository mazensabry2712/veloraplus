<?php

namespace App\Application\Company;

use App\Models\CompanySetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class CompanyProfileManager
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Tenant $tenant, array $attributes): Tenant
    {
        $profile = [
            'name' => trim((string) $attributes['name']),
            'legal_name' => $this->nullableString($attributes['legal_name'] ?? null),
            'industry' => $this->nullableString($attributes['industry'] ?? null),
            'business_type' => $this->nullableString($attributes['business_type'] ?? null),
            'country_code' => $this->nullableUpper($attributes['country_code'] ?? null),
            'default_currency' => strtoupper(trim((string) $attributes['default_currency'])),
            'timezone' => trim((string) $attributes['timezone']),
            'locale' => trim((string) $attributes['locale']),
            'phone' => $this->nullableString($attributes['phone'] ?? null),
            'email' => $this->nullableString($attributes['email'] ?? null),
            'website' => $this->nullableString($attributes['website'] ?? null),
            'city' => $this->nullableString($attributes['city'] ?? null),
            'address' => $this->nullableString($attributes['address'] ?? null),
        ];

        $tenant = DB::connection('central')->transaction(function () use ($tenant, $profile): Tenant {
            $tenant->fill($profile);
            $tenant->save();

            return $tenant->refresh();
        });

        $this->syncTenantSettings($profile);

        return $tenant;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function syncTenantSettings(array $profile): void
    {
        $settings = [
            'company.name' => [$profile['name'], 'string'],
            'company.legal_name' => [$profile['legal_name'], 'string'],
            'company.industry' => [$profile['industry'], 'string'],
            'company.business_type' => [$profile['business_type'], 'string'],
            'company.country_code' => [$profile['country_code'], 'string'],
            'company.default_currency' => [$profile['default_currency'], 'string'],
            'company.timezone' => [$profile['timezone'], 'string'],
            'company.locale' => [$profile['locale'], 'string'],
            'company.phone' => [$profile['phone'], 'string'],
            'company.email' => [$profile['email'], 'string'],
            'company.website' => [$profile['website'], 'string'],
            'company.city' => [$profile['city'], 'string'],
            'company.address' => [$profile['address'], 'string'],
        ];

        foreach ($settings as $key => [$value, $type]) {
            CompanySetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type],
            );
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableUpper(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        return $value === null ? null : strtoupper($value);
    }
}
