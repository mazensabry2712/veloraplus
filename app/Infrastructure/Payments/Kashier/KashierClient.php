<?php

namespace App\Infrastructure\Payments\Kashier;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class KashierClient
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPaymentSession(array $payload, ?array $credentials = null): array
    {
        $response = $this->apiRequest($credentials)->post('/v3/payment/sessions', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Kashier payment session creation failed: '.$response->body());
        }

        return $response->json();
    }

    public function getPaymentSessionPayment(string $sessionId, ?array $credentials = null): array
    {
        $response = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->withHeader('Authorization', $this->secretKey($credentials))
            ->timeout(15)
            ->retry(2, 200, throw: false)
            ->get("/v3/payment/sessions/{$sessionId}/payment");

        if ($response->failed()) {
            throw new RuntimeException('Kashier payment session lookup failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function refundOrder(string $orderId, array $payload, ?array $credentials = null): array
    {
        $response = $this->fepRequest($credentials)->put("/v3/orders/{$orderId}", $payload);

        if ($response->failed()) {
            throw new RuntimeException('Kashier refund request failed: '.$response->body());
        }

        return $response->json();
    }

    private function apiRequest(?array $credentials = null): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Authorization' => $this->secretKey($credentials),
                'api-key' => $this->paymentApiKey($credentials),
            ])
            ->timeout(15)
            ->retry(2, 200, throw: false);
    }

    private function fepRequest(?array $credentials = null): PendingRequest
    {
        return Http::baseUrl($this->fepBaseUrl())
            ->acceptJson()
            ->asJson()
            ->withHeader('Authorization', $this->secretKey($credentials))
            ->timeout(15)
            ->retry(2, 200, throw: false);
    }

    private function secretKey(?array $credentials = null): string
    {
        $value = (string) ($credentials['secret_key'] ?? config('velora.payments.kashier.secret_key'));

        if ($value === '') {
            throw new RuntimeException('Kashier secret key is not configured.');
        }

        return $value;
    }

    private function paymentApiKey(?array $credentials = null): string
    {
        $value = (string) ($credentials['payment_api_key'] ?? config('velora.payments.kashier.payment_api_key'));

        if ($value === '') {
            throw new RuntimeException('Kashier Payment API Key is not configured.');
        }

        return $value;
    }

    public function baseUrl(): string
    {
        return $this->mode() === 'live'
            ? rtrim((string) config('velora.payments.kashier.live_api_base_url'), '/')
            : rtrim((string) config('velora.payments.kashier.test_api_base_url'), '/');
    }

    public function fepBaseUrl(): string
    {
        return $this->mode() === 'live'
            ? rtrim((string) config('velora.payments.kashier.live_fep_base_url'), '/')
            : rtrim((string) config('velora.payments.kashier.test_fep_base_url'), '/');
    }

    private function mode(): string
    {
        return strtolower((string) config('velora.payments.kashier.mode', 'test')) === 'live'
            ? 'live'
            : 'test';
    }
}
