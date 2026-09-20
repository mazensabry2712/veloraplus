<?php

namespace App\Application\Dashboard;

use App\Application\Payments\TenantPaymentAccountManager;
use App\Models\PaymentProviderAccount;
use App\Models\Tenant;
use Illuminate\Support\Arr;
use RuntimeException;

final class TenantIntegrationSettingsService
{
    public function __construct(
        private readonly TenantPaymentAccountManager $accounts,
    ) {
    }

    public function tenantPaymentAccount(Tenant $tenant): ?PaymentProviderAccount
    {
        $provider = strtolower((string) config('velora.payments.tenant_provider'));

        return PaymentProviderAccount::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('provider', $provider)
            ->first();
    }

    /**
     * @return array{provider: string, account_reference: ?string, status: ?string}
     */
    public function tenantPaymentSummary(Tenant $tenant): array
    {
        $account = $this->tenantPaymentAccount($tenant);

        return [
            'provider' => strtolower((string) config('velora.payments.tenant_provider')),
            'account_reference' => $account?->account_reference,
            'status' => $account?->status,
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateTenantPayment(Tenant $tenant, array $attributes): PaymentProviderAccount
    {
        $provider = strtolower(trim((string) ($attributes['provider'] ?? config('velora.payments.tenant_provider'))));
        $existing = PaymentProviderAccount::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('provider', $provider)
            ->first();

        $incoming = Arr::only((array) ($attributes['credentials'] ?? []), [
            'merchant_id',
            'secret_key',
            'payment_api_key',
            'merchant_redirect_url',
            'webhook_url',
        ]);

        if ($existing !== null) {
            $merged = array_merge($existing->encrypted_credentials ?? [], array_filter(
                $incoming,
                static fn (mixed $value): bool => $value !== null && $value !== '',
            ));
        } else {
            $merged = $incoming;
        }

        return $this->accounts->upsert(
            tenant: $tenant,
            provider: $provider,
            accountReference: (string) $attributes['account_reference'],
            credentials: $merged,
            metadata: [],
            status: (string) ($attributes['status'] ?? 'active'),
        );
    }
}
