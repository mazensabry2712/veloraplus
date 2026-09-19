<?php

namespace App\Domain\Billing\Contracts;

interface RecurringPaymentGateway
{
    /** @return array<string, mixed> */
    public function createRecurringAgreement(array $payload): array;

    /** @return array<string, mixed> */
    public function chargeRecurring(array $payload): array;

    /** @return array<string, mixed> */
    public function cancelRecurring(array $payload): array;
}