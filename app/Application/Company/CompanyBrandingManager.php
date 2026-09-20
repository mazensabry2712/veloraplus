<?php

namespace App\Application\Company;

use App\Domain\Tenancy\TenantContext;
use App\Models\CompanySetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class CompanyBrandingManager
{
    /** @var list<string> */
    public const COLOR_KEYS = [
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'text_color',
    ];

    /**
     * @return array<string, ?string>
     */
    public function branding(): array
    {
        $keys = array_merge(
            array_map(fn (string $key): string => 'branding.'.$key, self::COLOR_KEYS),
            ['branding.logo_path', 'branding.favicon_path'],
        );

        $values = CompanySetting::query()
            ->whereIn('key', $keys)
            ->pluck('value', 'key');

        return [
            ...collect(self::COLOR_KEYS)
                ->mapWithKeys(fn (string $key): array => [$key => $values->get('branding.'.$key)])
                ->all(),
            'logo_url' => $this->publicUrl($values->get('branding.logo_path')),
            'favicon_url' => $this->publicUrl($values->get('branding.favicon_path')),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): void
    {
        $context = app(TenantContext::class);
        $tenant = $context->current();

        if ($tenant === null) {
            throw new RuntimeException('Company branding requires an active tenant context.');
        }

        $oldPaths = [];
        $newPaths = [];

        foreach (['logo', 'favicon'] as $fileKey) {
            $file = $attributes[$fileKey] ?? null;

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $newPaths[$fileKey] = $file->store(
                'tenants/'.$tenant->getKey().'/branding',
                'public',
            );
        }

        try {
            DB::connection('tenant')->transaction(function () use ($attributes, $newPaths, &$oldPaths): void {
                foreach (self::COLOR_KEYS as $key) {
                    if (array_key_exists($key, $attributes)) {
                        $this->putSetting('branding.'.$key, $attributes[$key]);
                    }
                }

                foreach (['logo', 'favicon'] as $fileKey) {
                    if (! array_key_exists($fileKey, $newPaths)) {
                        continue;
                    }

                    $settingKey = 'branding.'.$fileKey.'_path';
                    $oldPath = CompanySetting::query()->where('key', $settingKey)->value('value');

                    if (is_string($oldPath) && $oldPath !== '') {
                        $oldPaths[] = $oldPath;
                    }

                    $this->putSetting($settingKey, $newPaths[$fileKey]);
                }
            });
        } catch (\Throwable $exception) {
            foreach ($newPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        foreach ($oldPaths as $path) {
            if (! in_array($path, array_values($newPaths), true)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * @param  scalar|null  $value
     */
    private function putSetting(string $key, mixed $value): void
    {
        CompanySetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value === null ? null : (string) $value,
                'type' => 'string',
            ],
        );
    }

    private function publicUrl(mixed $path): ?string
    {
        $path = is_string($path) ? trim($path) : '';

        return $path === '' ? null : Storage::disk('public')->url($path);
    }
}
