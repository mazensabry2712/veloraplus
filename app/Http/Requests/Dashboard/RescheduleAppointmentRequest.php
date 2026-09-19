<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.appointments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
        ];
    }
}
