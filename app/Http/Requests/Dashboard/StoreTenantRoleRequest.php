<?php

namespace App\Http\Requests\Dashboard;

use App\Application\Authorization\TenantRbacBootstrapper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('members.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['required', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(
                    fn ($query) => $query->where('guard_name', TenantRbacBootstrapper::GUARD),
                ),
            ],
        ];
    }
}
