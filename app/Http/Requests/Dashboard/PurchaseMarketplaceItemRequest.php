<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class PurchaseMarketplaceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'catalog_type' => Str::lower(trim((string) $this->input('catalog_type'))),
            'catalog_key' => Str::lower(trim((string) $this->input('catalog_key'))),
            'quantity' => $this->input('quantity', 1),
        ]);
    }

    public function rules(): array
    {
        return [
            'catalog_type' => ['required', 'string', 'in:module,feature,bundle'],
            'catalog_key' => [
                'required',
                'string',
                'max:190',
                'regex:/^[a-z0-9][a-z0-9._-]*$/',
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
