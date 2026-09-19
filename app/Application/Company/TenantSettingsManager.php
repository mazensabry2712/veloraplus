<?php

namespace App\Application\Company;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TenantSettingsManager
{
    /** @var list<string> */
    public const SEO_KEYS = [
        'site_title',
        'site_description',
        'default_og_image',
        'robots',
        'locale',
    ];

    /**
     * @return array<string, ?string>
     */
    public function seo(): array
    {
        $values = CompanySetting::query()
            ->whereIn('key', $this->seoSettingKeys())
            ->pluck('value', 'key');

        return collect(self::SEO_KEYS)
            ->mapWithKeys(fn (string $key): array => [$key => $values->get('seo.'.$key)])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function updateSeo(array $settings): void
    {
        foreach (array_keys($settings) as $key) {
            if (! in_array($key, self::SEO_KEYS, true)) {
                throw new InvalidArgumentException('Unknown tenant SEO setting: '.$key);
            }
        }

        $settings = collect($settings)
            ->mapWithKeys(function (mixed $value, string $key): array {
                if ($value !== null && ! is_string($value)) {
                    throw new InvalidArgumentException('Tenant SEO settings must contain string or null values.');
                }

                return [$key => $value === null ? null : trim($value)];
            })
            ->all();

        DB::connection('tenant')->transaction(function () use ($settings): void {
            foreach ($settings as $key => $value) {
                CompanySetting::query()->updateOrCreate(
                    ['key' => 'seo.'.$key],
                    [
                        'value' => $value,
                        'type' => 'string',
                    ],
                );
            }
        });
    }

    /**
     * @return list<string>
     */
    private function seoSettingKeys(): array
    {
        return array_map(
            fn (string $key): string => 'seo.'.$key,
            self::SEO_KEYS,
        );
    }
}
