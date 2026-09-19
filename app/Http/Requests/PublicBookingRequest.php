<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_id' => [
                'nullable',
                'string',
                Rule::exists('staff', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'starts_at' => [
                'required',
                'date_format:Y-m-d\TH:i',
            ],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:190'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }

    public function service(): ?Service
    {
        $slug = (string) $this->route('slug');

        return Service::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->where('online_bookable', true)
            ->first();
    }
}
