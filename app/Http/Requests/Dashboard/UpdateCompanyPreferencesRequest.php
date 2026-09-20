<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCompanyPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'default_currency' => $this->filled('default_currency')
                ? strtoupper(trim((string) $this->input('default_currency')))
                : null,
            'timezone' => $this->filled('timezone')
                ? trim((string) $this->input('timezone'))
                : null,
            'locale' => $this->filled('locale')
                ? trim((string) $this->input('locale'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'default_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z]{2,8})?$/'],
        ];
    }
}
