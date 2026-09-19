<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Payments\TenantPaymentManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RefundTenantPaymentRequest;
use App\Models\Appointment;
use App\Models\TenantPayment;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class TenantPaymentController extends Controller
{
    public function createCheckout(
        Appointment $appointment,
        TenantPaymentManager $manager,
    ): RedirectResponse {
        Gate::authorize('create', TenantPayment::class);

        try {
            $manager->createCheckout($appointment);
        } catch (DomainException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Tenant payment checkout created successfully.');
    }

    public function reconcile(
        TenantPayment $payment,
        TenantPaymentManager $manager,
    ): RedirectResponse {
        Gate::authorize('manage', $payment);

        try {
            $manager->reconcile($payment);
        } catch (DomainException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Tenant payment reconciliation completed successfully.');
    }

    public function refund(
        RefundTenantPaymentRequest $request,
        TenantPayment $payment,
        TenantPaymentManager $manager,
    ): RedirectResponse {
        Gate::authorize('manage', $payment);

        try {
            $data = $request->validated();

            $manager->refund(
                payment: $payment,
                amountMinor: (int) $data['amount_minor'],
                reason: $data['reason'],
                idempotencyKey: $data['idempotency_key'] ?? null,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')->with('status', 'Tenant payment refund requested successfully.');
    }
}
