<?php

namespace App\Application\Booking;

use App\Application\Payments\TenantPaymentManager;
use App\Domain\Booking\AppointmentPaymentStatus;
use App\Domain\Tenancy\TenantContext;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\PaymentProviderAccount;
use App\Models\Service;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PublicBookingManager
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AppointmentManager $appointments,
        private readonly TenantPaymentManager $payments,
    ) {
    }

    /**
     * @return list<Staff>
     */
    public function availableStaff(Service $service): array
    {
        $this->assertPublicService($service);

        return Staff::query()
            ->with('location')
            ->where('status', 'active')
            ->whereHas('location', fn ($query) => $query->where('status', 'active'))
            ->whereHas('services', fn ($query) => $query->whereKey($service->getKey()))
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @param array{
     *     staff_id?: string|null,
     *     starts_at: string,
     *     customer_name: string,
     *     customer_phone: string,
     *     customer_email?: string|null,
     *     idempotency_key: string
     * } $data
     * @return array{appointment: Appointment, payment: \App\Models\TenantPayment|null, checkout_url: string|null}
     */
    public function book(Service $service, array $data): array
    {
        $this->assertContext();
        $this->assertPublicService($service);

        $idempotencyKey = trim((string) $data['idempotency_key']);

        if ($idempotencyKey === '') {
            throw new DomainException('Idempotency key is required.');
        }

        $existing = Appointment::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $this->existingBookingResult($existing);
        }

        $staff = $this->resolveStaff($service, $data['staff_id'] ?? null);
        $localStart = $this->normalizeLocalStart(
            (string) $data['starts_at'],
            (string) ($staff->location?->timezone ?: config('app.timezone', 'UTC')),
        );

        if ($localStart->lessThanOrEqualTo(CarbonImmutable::now($localStart->timezone))) {
            throw new DomainException('Booking time must be in the future.');
        }

        $amountMinor = (int) $service->price_minor;

        if ($amountMinor > 0) {
            $this->assertTenantPaymentAccountConfigured();
        }

        $customer = Customer::query()->create([
            'name' => trim($data['customer_name']),
            'phone' => trim($data['customer_phone']),
            'email' => isset($data['customer_email']) && $data['customer_email'] !== ''
                ? trim($data['customer_email'])
                : null,
            'status' => 'active',
            'source' => 'public_booking',
            'metadata' => [
                'public_booking' => true,
            ],
        ]);

        try {
            $appointment = $this->appointments->create(
                customer: $customer,
                staff: $staff,
                service: $service,
                startsAt: $localStart->toIso8601String(),
                attributes: [
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => [
                        'source' => 'public_booking',
                    ],
                ],
            );
        } catch (\Throwable $exception) {
            $customer->delete();

            throw $exception;
        }

        if ($amountMinor === 0) {
            $appointment->forceFill([
                'payment_status' => AppointmentPaymentStatus::Paid,
                'metadata' => array_merge($appointment->metadata ?? [], [
                    'payment_required' => false,
                    'payment_source' => 'free_service',
                ]),
            ])->save();

            return [
                'appointment' => $appointment->refresh(),
                'payment' => null,
                'checkout_url' => null,
            ];
        }

        try {
            $payment = $this->payments->createCheckout($appointment);
        } catch (\Throwable $exception) {
            return [
                'appointment' => $appointment->refresh(),
                'payment' => null,
                'checkout_url' => null,
            ];
        }

        $checkout = $payment->metadata['checkout'] ?? [];

        return [
            'appointment' => $appointment->refresh(),
            'payment' => $payment,
            'checkout_url' => is_string($checkout['checkout_url'] ?? null)
                ? $checkout['checkout_url']
                : null,
        ];
    }

    private function existingBookingResult(Appointment $appointment): array
    {
        $appointment->loadMissing('items');

        $payment = $appointment->payment_status === AppointmentPaymentStatus::Paid
            ? $appointment->payments()
                ->where('status', 'succeeded')
                ->latest()
                ->first()
            : $appointment->payments()
                ->whereIn('status', ['pending', 'succeeded'])
                ->latest()
                ->first();

        $checkoutUrl = $payment?->metadata['checkout']['checkout_url'] ?? null;

        return [
            'appointment' => $appointment->refresh(),
            'payment' => $payment,
            'checkout_url' => is_string($checkoutUrl) ? $checkoutUrl : null,
        ];
    }

    private function resolveStaff(Service $service, ?string $staffId): Staff
    {
        $staff = $this->availableStaff($service);

        if ($staff === []) {
            throw new DomainException('No active staff member is available for this service.');
        }

        if ($staffId !== null && trim($staffId) !== '') {
            foreach ($staff as $candidate) {
                if ((string) $candidate->getKey() === trim($staffId)) {
                    return $candidate;
                }
            }

            throw new DomainException('Selected staff member is not available for this service.');
        }

        if (count($staff) !== 1) {
            throw new DomainException('A staff member must be selected for this service.');
        }

        return $staff[0];
    }

    private function normalizeLocalStart(string $value, string $timezone): CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, $timezone);
        } catch (\Throwable) {
            throw new DomainException('Booking time has an invalid format.');
        }

        if ($parsed === false) {
            throw new DomainException('Booking time has an invalid format.');
        }

        return $parsed;
    }

    private function assertPublicService(Service $service): void
    {
        if ($service->trashed() || $service->status?->value !== 'active' || ! $service->online_bookable) {
            throw new DomainException('The selected service is not available for public booking.');
        }
    }

    private function assertContext(): void
    {
        if (! $this->context->check()) {
            throw new DomainException('Tenant context is required for Public Booking.');
        }
    }

    private function assertTenantPaymentAccountConfigured(): void
    {
        $tenant = $this->context->current();
        $provider = strtolower(trim((string) config('velora.payments.tenant_provider')));

        if ($provider === '') {
            throw new DomainException('Tenant payment provider is not configured.');
        }

        $exists = PaymentProviderAccount::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('provider', $provider)
            ->where('status', 'active')
            ->exists();

        if (! $exists) {
            throw new DomainException('Online payment is temporarily unavailable for this service.');
        }
    }
}
