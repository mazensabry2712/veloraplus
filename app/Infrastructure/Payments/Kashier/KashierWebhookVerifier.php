<?php

namespace App\Infrastructure\Payments\Kashier;

use App\Infrastructure\Payments\Kashier\Exceptions\InvalidWebhookSignature;
use DomainException;

final class KashierWebhookVerifier
{
    /**
     * @param array<string, mixed> $data
     */
    public function verify(array $data, ?string $signature, ?string $apiKeyOverride = null): void
    {
        $apiKey = (string) ($apiKeyOverride ?? config('velora.payments.kashier.payment_api_key'));

        if ($apiKey === '' || $signature === null || trim($signature) === '') {
            throw new InvalidWebhookSignature('Kashier webhook signature is missing.');
        }

        $keys = $data['signatureKeys'] ?? null;

        if (! is_array($keys) || $keys === []) {
            throw new InvalidWebhookSignature('Kashier webhook signatureKeys are missing.');
        }

        $keys = array_values(array_filter($keys, 'is_string'));
        sort($keys, SORT_STRING);

        $parts = [];

        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_array($value) || is_object($value)) {
                throw new DomainException("Unsupported signed webhook value for [{$key}].");
            }

            $parts[] = $key.'='.rawurlencode($value === null ? '' : (string) $value);
        }

        $expected = hash_hmac('sha256', implode('&', $parts), $apiKey);

        if (! hash_equals(strtolower($expected), strtolower(trim($signature)))) {
            throw new InvalidWebhookSignature('Kashier webhook signature is invalid.');
        }
    }
}
