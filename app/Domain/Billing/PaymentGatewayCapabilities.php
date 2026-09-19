<?php

namespace App\Domain\Billing;

final readonly class PaymentGatewayCapabilities
{
    /**
     * @param list<string> $capabilities
     */
    public function __construct(
        public array $capabilities,
    ) {
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }
}