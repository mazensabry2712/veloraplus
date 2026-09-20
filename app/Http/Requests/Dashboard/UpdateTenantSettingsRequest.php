<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    public function rules(): array
    {
        $socialUrl = ['nullable', 'url:http,https', 'max:2048'];

        return [
            'seo' => ['sometimes', 'array:site_title,site_description,default_og_image,robots,locale'],
            'seo.site_title' => ['nullable', 'string', 'max:180'],
            'seo.site_description' => ['nullable', 'string', 'max:320'],
            'seo.default_og_image' => ['nullable', 'url:http,https', 'max:2048'],
            'seo.robots' => [
                'nullable',
                'string',
                Rule::in([
                    'index,follow',
                    'noindex,nofollow',
                    'index,nofollow',
                    'noindex,follow',
                ]),
            ],
            'seo.locale' => [
                'nullable',
                'string',
                'max:16',
                'regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z]{2,8})?$/',
            ],

            'social' => ['sometimes', 'array:facebook,instagram,linkedin,youtube,tiktok,x,whatsapp'],
            'social.facebook' => $socialUrl,
            'social.instagram' => $socialUrl,
            'social.linkedin' => $socialUrl,
            'social.youtube' => $socialUrl,
            'social.tiktok' => $socialUrl,
            'social.x' => $socialUrl,
            'social.whatsapp' => $socialUrl,

            'tax' => ['sometimes', 'array:enabled,rate_bps,registration_number'],
            'tax.enabled' => ['nullable', 'boolean'],
            'tax.rate_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'tax.registration_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $seo = $this->normalizeStringArray(
            is_array($this->input('seo')) ? $this->input('seo') : [],
        );

        $social = $this->normalizeStringArray(
            is_array($this->input('social')) ? $this->input('social') : [],
        );

        $tax = is_array($this->input('tax')) ? $this->input('tax') : [];

        if (array_key_exists('enabled', $tax) && is_string($tax['enabled'])) {
            $tax['enabled'] = filter_var($tax['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if (array_key_exists('rate_bps', $tax) && is_string($tax['rate_bps']) && ctype_digit($tax['rate_bps'])) {
            $tax['rate_bps'] = (int) $tax['rate_bps'];
        }

        if (array_key_exists('registration_number', $tax) && is_string($tax['registration_number'])) {
            $tax['registration_number'] = trim($tax['registration_number']);

            if ($tax['registration_number'] === '') {
                $tax['registration_number'] = null;
            }
        }

        $this->merge([
            'seo' => $seo,
            'social' => $social,
            'tax' => $tax,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizeStringArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = trim($value);

                if ($values[$key] === '') {
                    $values[$key] = null;
                }
            }
        }

        return $values;
    }
}
