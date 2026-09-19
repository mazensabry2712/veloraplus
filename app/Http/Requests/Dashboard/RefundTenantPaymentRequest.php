<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class RefundTenantPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.payments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ];
    }
}
