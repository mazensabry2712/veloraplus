<?php

namespace App\Application\Payments;

use App\Domain\Payments\Contracts\TenantPaymentGateway;
use App\Domain\Payments\Contracts\WebhookGateway;
use App\Domain\Tenancy\TenantContext;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\PaymentProviderAccount;
use App\Models\Tenant;
use App\Models\TenantPayment;
use DomainException;

final class KashierTenantWebhookHandler
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly TenantPaymentManager $payments,
        private readonly TenantDatabaseManager $databaseManager,
        private readonly TenantContext $context,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string|string[]|null> $headers
     * @return array{duplicate: bool, status: string}
     */
    public function handle(array $payload, array $headers, string $rawBody): array
    {
        $merchantOrderId = $this->extractMerchantOrderId($payload);
        $tenantId = $this->extractTenantId($merchantOrderId);

        $tenant = Tenant::query()
            ->whereKey($tenantId)
            ->whereIn('status', ['active', 'trial'])
            ->where('database_status', 'ready')
            ->first();

        if ($tenant === null) {
            throw new DomainException('Tenant payment webhook target was not found.');
        }

        $account = PaymentProviderAccount::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('provider', 'kashier')
            ->whereIn('status', ['active', 'inactive'])
            ->first();

        if ($account === null || ! is_array($account->encrypted_credentials ?? null)) {
            throw new DomainException('Tenant payment provider account was not found.');
        }

        $this->context->set($tenant);
        $this->databaseManager->connect($tenant);

        try {
            $gateway = $this->gateways->tenant('kashier');

            if (! $gateway instanceof TenantPaymentGateway || ! $gateway instanceof WebhookGateway) {
                throw new DomainException('Kashier Tenant webhook capability is unavailable.');
            }

            $event = $gateway->verifyWebhook(
                $payload,
                $headers,
                $account->encrypted_credentials,
            );

            if ((string) ($event['merchant_order_id'] ?? '') !== $merchantOrderId) {
                throw new DomainException('Tenant payment webhook merchant order does not match the target payment.');
            }

            $payment = TenantPayment::query()
                ->where('merchant_order_id', $merchantOrderId)
                ->where('provider', 'kashier')
                ->first();

            if ($payment === null) {
                throw new DomainException('Tenant payment webhook payment was not found.');
            }

            $eventId = hash('sha256', implode('|', [
                'kashier',
                $event['event'] ?? '',
                $event['status'] ?? '',
                $event['merchant_order_id'] ?? '',
                $event['transaction_id'] ?? '',
                $event['kashier_order_id'] ?? '',
            ]));

            return $this->payments->processVerifiedWebhook(
                $payment,
                $event,
                $payload,
                $eventId,
                hash('sha256', $rawBody),
            );
        } finally {
            $this->databaseManager->disconnect();
            $this->context->clear();
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractMerchantOrderId(array $payload): string
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new DomainException('Tenant payment webhook data is missing.');
        }

        $merchantOrderId = trim((string) ($data['merchantOrderId'] ?? ''));

        if ($merchantOrderId === '') {
            throw new DomainException('Tenant payment webhook merchant order id is missing.');
        }

        return $merchantOrderId;
    }

    private function extractTenantId(string $merchantOrderId): string
    {
        [$tenantId] = explode('.', $merchantOrderId, 2);

        if (
            $tenantId === ''
            || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $tenantId) !== 1
        ) {
            throw new DomainException('Tenant payment webhook merchant order id is invalid.');
        }

        return strtolower($tenantId);
    }
}
