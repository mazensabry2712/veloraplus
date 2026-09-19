<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffWorkingHourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.availability.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'starts_at' => ['required', 'date_format:H:i,H:i:s'],
            'ends_at' => ['required', 'date_format:H:i,H:i:s'],
        ];
    }
}
