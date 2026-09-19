<?php

namespace App\Application\Payments;

use App\Domain\Payments\Contracts\PlatformPaymentGateway;
use App\Domain\Payments\Contracts\TenantPaymentGateway;
use DomainException;

final class PaymentGatewayManager
{
    public function platform(?string $provider = null): PlatformPaymentGateway
    {
        $name = strtolower(trim($provider ?? (string) config('velora.payments.platform_provider')));
        $gateway = $this->resolve($name);

        if (! $gateway instanceof PlatformPaymentGateway) {
            throw new DomainException("Payment provider [{$name}] does not support Platform Billing.");
        }

        return $gateway;
    }

    public function tenant(?string $provider = null): TenantPaymentGateway
    {
        $name = strtolower(trim($provider ?? (string) config('velora.payments.tenant_provider')));
        $gateway = $this->resolve($name);

        if (! $gateway instanceof TenantPaymentGateway) {
            throw new DomainException("Payment provider [{$name}] does not support Tenant Payments.");
        }

        return $gateway;
    }

    private function resolve(string $provider): object
    {
        $driver = config("velora.payments.drivers.{$provider}");

        if (! is_string($driver) || $driver === '') {
            throw new DomainException("Unsupported payment provider [{$provider}].");
        }

        return app($driver);
    }
}
