<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('locations.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? Str::upper(trim((string) $this->input('code'))) : null,
            'country_code' => $this->filled('country_code')
                ? Str::upper(trim((string) $this->input('country_code')))
                : null,
            'status' => $this->filled('status') ? Str::lower(trim((string) $this->input('status'))) : 'active',
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/'],
            'address' => ['nullable', 'string'],
            'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'city' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'string', 'timezone'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
