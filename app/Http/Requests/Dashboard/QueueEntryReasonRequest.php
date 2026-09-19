<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class QueueEntryReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.queues.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
