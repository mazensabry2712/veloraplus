<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.appointments.manage') ?? false;
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
            'staff_id' => [
                'required',
                'string',
                Rule::exists(Staff::class, 'id')->where(
                    fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'),
                ),
            ],
            'service_id' => [
                'required',
                'string',
                Rule::exists(Service::class, 'id')->where(
                    fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'),
                ),
            ],
            'starts_at' => ['required', 'date'],
            'location_id' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ];
    }
}
