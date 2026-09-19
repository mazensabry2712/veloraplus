<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class IssuePlatformCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'source' => ['nullable', 'string', 'max:191'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
