<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class RequestSubscriptionUpgrade extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->map(function (mixed $item): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                return [
                    ...$item,
                    'catalog_type' => isset($item['catalog_type'])
                        ? Str::lower(trim((string) $item['catalog_type']))
                        : null,
                    'catalog_key' => isset($item['catalog_key'])
                        ? Str::lower(trim((string) $item['catalog_key']))
                        : null,
                    'quantity' => isset($item['quantity']) ? (int) $item['quantity'] : 1,
                ];
            })
            ->all();

        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.catalog_type' => ['required', 'string', 'in:module,feature,bundle'],
            'items.*.catalog_key' => [
                'required',
                'string',
                'max:190',
                'regex:/^[a-z0-9][a-z0-9._-]*$/',
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
