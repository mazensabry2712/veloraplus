<?php

namespace App\Domain\Payments\Contracts;

interface RecurringPaymentGateway
{
    /** @return array<string, mixed> */
    public function createRecurringAgreement(array $context): array;

    /** @return array<string, mixed> */
    public function chargeRecurring(array $context): array;

    /** @return array<string, mixed> */
    public function cancelRecurring(array $context): array;
}
