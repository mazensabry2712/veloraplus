<?php

namespace App\Domain\Payments\Contracts;

interface WebhookGateway
{
    /** @return array<string, mixed> */
    public function verifyWebhook(array $payload, array $headers): array;
}
