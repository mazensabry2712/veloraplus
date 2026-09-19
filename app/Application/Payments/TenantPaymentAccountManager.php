<?php

namespace App\Application\Payments;

use App\Models\PaymentProviderAccount;
use App\Models\Tenant;
use DomainException;

final class TenantPaymentAccountManager
{
    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $metadata
     */
    public function upsert(
        Tenant $tenant,
        string $provider,
        string $accountReference,
        array $credentials,
        array $metadata = [],
        string $status = 'active',
    ): PaymentProviderAccount {
        $provider = strtolower(trim($provider));
        $accountReference = trim($accountReference);

        if ($provider === '' || $accountReference === '') {
            throw new DomainException('Payment provider and account reference are required.');
        }

        if ($credentials === [] && $status === 'active') {
            throw new DomainException('Active Tenant payment accounts require credentials.');
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new DomainException('Invalid Tenant payment account status.');
        }

        return PaymentProviderAccount::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'provider' => $provider,
            ],
            [
                'account_reference' => $accountReference,
                'status' => $status,
                'encrypted_credentials' => $credentials,
                'metadata' => $metadata,
            ],
        );
    }

    public function deactivate(PaymentProviderAccount $account): PaymentProviderAccount
    {
        $account->update(['status' => 'inactive']);

        return $account->refresh();
    }
}
