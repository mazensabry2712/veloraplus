<?php

namespace App\Application\Payments;

use App\Application\Billing\PaymentService;
use App\Domain\Payments\Contracts\PlatformPaymentGateway;
use App\Domain\Payments\Contracts\WebhookGateway;
use App\Models\PlatformPayment;
use App\Models\WebhookEvent;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class KashierWebhookHandler
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly PaymentService $payments,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string|string[]|null> $headers
     * @return array{duplicate: bool, status: string}
     */
    public function handle(array $payload, array $headers, string $rawBody): array
    {
        $gateway = $this->gateways->platform('kashier');

        if (! $gateway instanceof PlatformPaymentGateway || ! $gateway instanceof WebhookGateway) {
            throw new DomainException('Kashier platform webhook capability is unavailable.');
        }

        $event = $gateway->verifyWebhook($payload, $headers);
        $eventId = hash('sha256', implode('|', [
            $event['provider'],
            $event['event'],
            $event['status'],
            $event['merchant_order_id'] ?? '',
            $event['transaction_id'] ?? '',
            $event['kashier_order_id'] ?? '',
        ]));

        $payloadHash = hash('sha256', $rawBody);

        DB::connection('central')->transaction(function () use ($event, $eventId, $payloadHash, $payload): void {
            WebhookEvent::query()->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'provider' => 'kashier',
                'provider_event_id' => $eventId,
                'event_type' => $event['event'] ?: 'unknown',
                'status' => 'received',
                'received_at' => CarbonImmutable::now(),
                'payload_hash' => $payloadHash,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
        });

        return DB::connection('central')->transaction(function () use ($event, $eventId): array {
            $stored = WebhookEvent::query()
                ->where('provider', 'kashier')
                ->where('provider_event_id', $eventId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($stored->processed_at !== null) {
                return ['duplicate' => true, 'status' => 'processed'];
            }

            if ($event['event'] !== 'pay') {
                $stored->update([
                    'status' => 'ignored',
                    'processed_at' => CarbonImmutable::now(),
                ]);

                return ['duplicate' => false, 'status' => 'ignored'];
            }

            $merchantOrderId = trim((string) ($event['merchant_order_id'] ?? ''));

            if ($merchantOrderId === '') {
                throw new DomainException('Kashier webhook merchant order id is missing.');
            }

            $payment = PlatformPayment::query()
                ->lockForUpdate()
                ->whereKey($merchantOrderId)
                ->where('provider', 'kashier')
                ->first();

            if ($payment === null) {
                throw new DomainException('Kashier webhook payment was not found.');
            }

            $status = strtoupper((string) ($event['status'] ?? ''));

            if ($status === 'SUCCESS') {
                $this->assertAmountMatches(
                    $payment,
                    (string) ($event['amount'] ?? ''),
                    (string) ($event['currency'] ?? ''),
                );

                $this->payments->markSucceeded(
                    $payment,
                    $this->paymentTime($event),
                    (string) ($event['transaction_id'] ?? ''),
                    $eventId,
                    [
                        'kashier_order_id' => $event['kashier_order_id'] ?? null,
                        'merchant_order_id' => $merchantOrderId,
                        'order_reference' => $event['order_reference'] ?? null,
                        'webhook_status' => $status,
                    ],
                );
            } elseif ($status === 'FAILURE') {
                $this->payments->markFailed($payment, 'Kashier webhook failure');
            } elseif ($status !== 'PENDING') {
                throw new DomainException("Unsupported Kashier payment status [{$status}].");
            }

            $stored->update([
                'status' => $status === 'SUCCESS' ? 'processed' : 'ignored',
                'processed_at' => CarbonImmutable::now(),
            ]);

            return ['duplicate' => false, 'status' => $status];
        });
    }

    private function assertAmountMatches(
        PlatformPayment $payment,
        string $amount,
        string $currency,
    ): void {
        if (strtoupper($currency) !== strtoupper($payment->currency)) {
            throw new DomainException('Kashier payment currency does not match the platform payment.');
        }

        if ($this->decimalToMinor($amount) !== $payment->amount_minor) {
            throw new DomainException('Kashier payment amount does not match the platform payment.');
        }
    }

    private function decimalToMinor(string $amount): int
    {
        $amount = trim($amount);

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new DomainException('Kashier payment amount has an invalid format.');
        }

        $whole = (int) $matches[1];

        if ($whole > intdiv(PHP_INT_MAX, 100)) {
            throw new DomainException('Kashier payment amount exceeds supported range.');
        }

        return ($whole * 100) + (int) str_pad($matches[2] ?? '', 2, '0', STR_PAD_RIGHT);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function paymentTime(array $event): CarbonImmutable
    {
        $timestamp = $event['data']['creationDate'] ?? $event['data']['timestamp'] ?? null;

        return is_string($timestamp) ? CarbonImmutable::parse($timestamp) : CarbonImmutable::now();
    }
}
