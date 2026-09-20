<?php

namespace App\Application\Company;

use App\Models\CompanySetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class CompanyPreferencesManager
{
    /**
     * @param array{default_currency:string,timezone:string,locale:string} $attributes
     */
    public function update(Tenant $tenant, array $attributes): Tenant
    {
        $preferences = [
            'default_currency' => strtoupper(trim($attributes['default_currency'])),
            'timezone' => trim($attributes['timezone']),
            'locale' => trim($attributes['locale']),
        ];

        $tenant = DB::connection('central')->transaction(function () use ($tenant, $preferences): Tenant {
            $tenant->fill($preferences);
            $tenant->save();

            return $tenant->refresh();
        });

        foreach ($preferences as $key => $value) {
            CompanySetting::query()->updateOrCreate(
                ['key' => 'company.'.$key],
                ['value' => $value, 'type' => 'string'],
            );
        }

        return $tenant;
    }
}
