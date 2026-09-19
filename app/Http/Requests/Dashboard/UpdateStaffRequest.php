<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? Str::lower(trim((string) $this->input('email'))) : null,
            'status' => $this->filled('status') ? Str::lower(trim((string) $this->input('status'))) : 'active',
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:190'],
            'location_id' => [
                'nullable',
                'string',
                Rule::exists('locations', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
