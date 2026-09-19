<?php

namespace App\Domain\Billing\Contracts;

interface TransactionLookupGateway
{
    /** @return array<string, mixed> */
    public function retrieveTransaction(string $externalId): array;
}