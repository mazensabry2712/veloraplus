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
            'email' => $this->filled('email')
                ? trim((string) $this->input('email'))
                : null,
            'website' => $this->filled('website')
                ? trim((string) $this->input('website'))
                : null,
            'phone' => $this->filled('phone')
                ? trim((string) $this->input('phone'))
                : null,
            'city' => $this->filled('city')
                ? trim((string) $this->input('city'))
                : null,
            'address' => $this->filled('address')
                ? trim((string) $this->input('address'))
                : null,
            'business_type' => $this->filled('business_type')
                ? trim((string) $this->input('business_type'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'default_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z]{2,8})?$/'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:2048'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
