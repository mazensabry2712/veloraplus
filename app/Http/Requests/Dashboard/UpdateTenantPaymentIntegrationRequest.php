<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantPaymentIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $credentials = is_array($this->input('credentials'))
            ? $this->input('credentials')
            : [];

        foreach ($credentials as $key => $value) {
            if (is_string($value)) {
                $credentials[$key] = trim($value);

                if ($credentials[$key] === '') {
                    $credentials[$key] = null;
                }
            }
        }

        $this->merge([
            'provider' => strtolower(trim((string) ($this->input('provider') ?? config('velora.payments.tenant_provider')))),
            'account_reference' => trim((string) $this->input('account_reference')),
            'credentials' => $credentials,
        ]);
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'max:64', Rule::in(array_keys(config('velora.payments.drivers', [])))],
            'account_reference' => ['required', 'string', 'max:191'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'credentials' => ['sometimes', 'array:merchant_id,secret_key,payment_api_key,merchant_redirect_url,webhook_url'],
            'credentials.merchant_id' => ['nullable', 'string', 'max:255'],
            'credentials.secret_key' => ['nullable', 'string', 'max:2048'],
            'credentials.payment_api_key' => ['nullable', 'string', 'max:2048'],
            'credentials.merchant_redirect_url' => ['nullable', 'url:http,https', 'max:2048'],
            'credentials.webhook_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
