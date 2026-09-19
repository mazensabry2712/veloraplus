<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateBookingServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.services.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['name', 'description', 'seo_title', 'seo_description', 'social_image_url'] as $key) {
            if ($this->has($key) && is_string($this->input($key))) {
                $normalized[$key] = trim((string) $this->input($key));
            }
        }

        if ($this->has('slug') && is_string($this->input('slug'))) {
            $normalized['slug'] = Str::slug((string) $this->input('slug'));
        }

        if ($this->has('currency')) {
            $normalized['currency'] = Str::upper(trim((string) $this->input('currency')));
        }

        if ($this->has('status')) {
            $normalized['status'] = Str::lower(trim((string) $this->input('status')));
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:320'],
            'social_image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'min:1'],
            'buffer_before_minutes' => ['sometimes', 'required', 'integer', 'min:0'],
            'buffer_after_minutes' => ['sometimes', 'required', 'integer', 'min:0'],
            'price_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'deposit_amount_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
            'online_bookable' => ['sometimes', 'required', 'boolean'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1'],
        ];
    }
}
