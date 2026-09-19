<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffBreakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.availability.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date_format:H:i,H:i:s'],
            'ends_at' => ['required', 'date_format:H:i,H:i:s'],
            'label' => ['nullable', 'string', 'max:150'],
        ];
    }
}
