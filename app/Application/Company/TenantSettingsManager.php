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

    /** @var list<string> */
    public const SOCIAL_KEYS = [
        'facebook',
        'instagram',
        'linkedin',
        'youtube',
        'tiktok',
        'x',
        'whatsapp',
    ];

    /** @var list<string> */
    public const TAX_KEYS = [
        'enabled',
        'rate_bps',
        'registration_number',
    ];

    /**
     * @return array<string, ?string>
     */
    public function seo(): array
    {
        return $this->readNamespace('seo', self::SEO_KEYS);
    }

    /**
     * @return array<string, ?string>
     */
    public function social(): array
    {
        return $this->readNamespace('social', self::SOCIAL_KEYS);
    }

    /**
     * @return array<string, ?string>
     */
    public function tax(): array
    {
        return $this->readNamespace('tax', self::TAX_KEYS);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function updateSeo(array $settings): void
    {
        $this->validateKeys($settings, self::SEO_KEYS, 'SEO');
        $this->persist('seo', $settings);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function updateSocial(array $settings): void
    {
        $this->validateKeys($settings, self::SOCIAL_KEYS, 'social media');
        $this->persist('social', $settings);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function updateTax(array $settings): void
    {
        $this->validateKeys($settings, self::TAX_KEYS, 'tax');

        if (array_key_exists('enabled', $settings) && ! is_bool($settings['enabled'])) {
            throw new InvalidArgumentException('Tax enabled setting must be boolean.');
        }

        if (array_key_exists('rate_bps', $settings) && ! is_int($settings['rate_bps'])) {
            throw new InvalidArgumentException('Tax rate must be an integer basis-point value.');
        }

        if (array_key_exists('registration_number', $settings)
            && $settings['registration_number'] !== null
            && ! is_string($settings['registration_number'])) {
            throw new InvalidArgumentException('Tax registration number must be a string or null.');
        }

        $normalized = [];

        foreach ($settings as $key => $value) {
            $normalized[$key] = match ($key) {
                'enabled' => $value,
                'rate_bps' => $value,
                'registration_number' => $value === null ? null : trim($value),
                default => $value,
            };
        }

        $this->persist('tax', $normalized);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<string>  $allowedKeys
     */
    private function validateKeys(array $settings, array $allowedKeys, string $label): void
    {
        foreach (array_keys($settings) as $key) {
            if (! in_array($key, $allowedKeys, true)) {
                throw new InvalidArgumentException('Unknown tenant '.$label.' setting: '.$key);
            }
        }

        foreach ($settings as $value) {
            if ($value !== null && ! is_string($value) && ! is_int($value) && ! is_bool($value)) {
                throw new InvalidArgumentException('Tenant '.$label.' settings contain an unsupported value type.');
            }
        }
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, ?string>
     */
    private function readNamespace(string $namespace, array $keys): array
    {
        $values = CompanySetting::query()
            ->whereIn('key', array_map(
                fn (string $key): string => $namespace.'.'.$key,
                $keys
            ))
            ->pluck('value', 'key');

        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [$key => $values->get($namespace.'.'.$key)])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function persist(string $namespace, array $settings): void
    {
        DB::connection('tenant')->transaction(function () use ($namespace, $settings): void {
            foreach ($settings as $key => $value) {
                CompanySetting::query()->updateOrCreate(
                    ['key' => $namespace.'.'.$key],
                    [
                        'value' => $value === null ? null : (string) $value,
                        'type' => match (true) {
                            is_bool($value) => 'boolean',
                            is_int($value) => 'integer',
                            default => 'string',
                        },
                    ],
                );
            }
        });
    }
}
