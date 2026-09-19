<?php

namespace App\Domain\Payments\Contracts;

interface TransactionLookupGateway
{
    /** @return array<string, mixed> */
    public function retrieveTransaction(string $reference, ?array $credentials = null): array;
}
