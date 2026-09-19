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
        return [
            'seo' => [
                'required',
                'array:site_title,site_description,default_og_image,robots,locale',
            ],
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $seo = is_array($this->input('seo')) ? $this->input('seo') : [];

        foreach ($seo as $key => $value) {
            $seo[$key] = is_string($value) ? trim($value) : $value;

            if ($seo[$key] === '') {
                $seo[$key] = null;
            }
        }

        $this->merge([
            'seo' => $seo,
        ]);
    }
}
