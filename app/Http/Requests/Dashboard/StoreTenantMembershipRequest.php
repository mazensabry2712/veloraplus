<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreTenantMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('members.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? Str::lower(trim((string) $this->input('email'))) : null,
            'role_key' => $this->filled('role_key') ? trim((string) $this->input('role_key')) : 'staff',
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:190'],
            'role_key' => ['required', 'string', 'max:100'],
        ];
    }
}
