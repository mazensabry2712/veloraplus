<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('members.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->filled('role_key')) {
            $normalized['role_key'] = trim((string) $this->input('role_key'));
        }

        if ($this->filled('status')) {
            $normalized['status'] = strtolower(trim((string) $this->input('status')));
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'role_key' => ['sometimes', 'required', 'string', 'max:100'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
        ];
    }
}
