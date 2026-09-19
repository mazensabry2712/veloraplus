<?php

namespace App\Application\Billing\Payments;

use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\Contracts\PlatformPaymentGatewayInterface;
use App\Domain\Billing\Contracts\TenantPaymentGatewayInterface;
use App\Domain\Billing\PaymentGatewayContext;
use DomainException;

final class PaymentGatewayManager
{
    public function platform(string $key): PlatformPaymentGatewayInterface
    {
        $gateway = $this->resolve(PaymentGatewayContext::Platform, $key);

        if (! $gateway instanceof PlatformPaymentGatewayInterface) {
            throw new DomainException("Gateway {$key} is not registered for platform billing.");
        }

        return $gateway;
    }

    public function tenant(string $key): TenantPaymentGatewayInterface
    {
        $gateway = $this->resolve(PaymentGatewayContext::Tenant, $key);

        if (! $gateway instanceof TenantPaymentGatewayInterface) {
            throw new DomainException("Gateway {$key} is not registered for tenant payments.");
        }

        return $gateway;
    }

    private function resolve(PaymentGatewayContext $context, string $key): PaymentGatewayInterface
    {
        $class = config("billing.payment_gateways.{$context->value}.{$key}");

        if (! is_string($class) || $class === '') {
            throw new DomainException("Payment gateway {$key} is not configured for {$context->value} payments.");
        }

        $gateway = app($class);

        if (! $gateway instanceof PaymentGatewayInterface) {
            throw new DomainException("Configured gateway {$key} must implement PaymentGatewayInterface.");
        }

        return $gateway;
    }
}