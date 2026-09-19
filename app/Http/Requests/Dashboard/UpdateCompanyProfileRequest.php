<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateCompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('company.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => $this->filled('country_code')
                ? Str::upper(trim((string) $this->input('country_code')))
                : null,
            'default_currency' => $this->filled('default_currency')
                ? Str::upper(trim((string) $this->input('default_currency')))
                : null,
            'locale' => $this->filled('locale')
                ? trim((string) $this->input('locale'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'default_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z]{2,8})?$/'],
        ];
    }
}
