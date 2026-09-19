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
    public function createPaymentSession(array $payload): array
    {
        $response = $this->apiRequest()->post('/v3/payment/sessions', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Kashier payment session creation failed: '.$response->body());
        }

        return $response->json();
    }

    public function getPaymentSessionPayment(string $sessionId): array
    {
        $response = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->withHeader('Authorization', $this->secretKey())
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
    public function refundOrder(string $orderId, array $payload): array
    {
        $response = $this->fepRequest()->put("/v3/orders/{$orderId}", $payload);

        if ($response->failed()) {
            throw new RuntimeException('Kashier refund request failed: '.$response->body());
        }

        return $response->json();
    }

    private function apiRequest(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Authorization' => $this->secretKey(),
                'api-key' => $this->paymentApiKey(),
            ])
            ->timeout(15)
            ->retry(2, 200, throw: false);
    }

    private function fepRequest(): PendingRequest
    {
        return Http::baseUrl($this->fepBaseUrl())
            ->acceptJson()
            ->asJson()
            ->withHeader('Authorization', $this->secretKey())
            ->timeout(15)
            ->retry(2, 200, throw: false);
    }

    private function secretKey(): string
    {
        $value = (string) config('velora.payments.kashier.secret_key');

        if ($value === '') {
            throw new RuntimeException('Kashier secret key is not configured.');
        }

        return $value;
    }

    private function paymentApiKey(): string
    {
        $value = (string) config('velora.payments.kashier.payment_api_key');

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
