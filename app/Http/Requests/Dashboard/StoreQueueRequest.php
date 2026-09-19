<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Location;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.queues.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'location_id' => [
                'required',
                'string',
                Rule::exists(Location::class, 'id')->where(
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
            'business_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
