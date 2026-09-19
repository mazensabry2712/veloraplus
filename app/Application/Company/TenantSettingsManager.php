<?php

namespace App\Application\Company;

use App\Models\CompanySetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
