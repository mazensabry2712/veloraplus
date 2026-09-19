<?php

namespace App\Http\Requests\Dashboard;

use App\Domain\Booking\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreBookingServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.services.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->filled('slug') ? Str::slug((string) $this->input('slug')) : null,
            'currency' => $this->filled('currency') ? Str::upper(trim((string) $this->input('currency'))) : null,
            'status' => $this->filled('status')
                ? Str::lower(trim((string) $this->input('status')))
                : ServiceStatus::Active->value,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'social_image_url' => ['nullable', 'url', 'max:2048'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'buffer_before_minutes' => ['nullable', 'integer', 'min:0'],
            'buffer_after_minutes' => ['nullable', 'integer', 'min:0'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'deposit_amount_minor' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
            'online_bookable' => ['required', 'boolean'],
            'capacity' => ['required', 'integer', 'min:1'],
        ];
    }
}
