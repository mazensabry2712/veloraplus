<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color'] as $key) {
            if ($this->filled($key)) {
                $this->merge([
                    $key => strtoupper(trim((string) $this->input($key))),
                ]);
            }
        }
    }

    public function rules(): array
    {
        $colorRule = ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/i'];

        return [
            'primary_color' => $colorRule,
            'secondary_color' => $colorRule,
            'accent_color' => $colorRule,
            'background_color' => $colorRule,
            'text_color' => $colorRule,
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'favicon' => ['nullable', 'image', 'mimes:png,webp', 'max:1024'],
        ];
    }
}
