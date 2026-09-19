<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Appointment;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnqueueQueueEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.queues.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'string',
                Rule::exists(Customer::class, 'id')->where(
                    fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'),
                ),
            ],
            'appointment_id' => [
                'nullable',
                'string',
                Rule::exists(Appointment::class, 'id'),
            ],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
