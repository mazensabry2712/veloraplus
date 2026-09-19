<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class CancelSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.manage') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
