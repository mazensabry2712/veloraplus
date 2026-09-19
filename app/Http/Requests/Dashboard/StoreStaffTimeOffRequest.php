<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffTimeOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.availability.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date_format:Y-m-d\\TH:iP,Y-m-d\\TH:i:sP'],
            'ends_at' => ['required', 'date_format:Y-m-d\\TH:iP,Y-m-d\\TH:i:sP'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
