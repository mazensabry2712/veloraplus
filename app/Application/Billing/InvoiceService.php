<?php

namespace App\Application\Billing;

use App\Domain\Billing\BillingMoney;
use App\Domain\Billing\InvoiceStatus;
use App\Models\PlatformInvoice;
use App\Models\PlatformInvoiceItem;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Str;

final class InvoiceService
{
    public function __construct(
        private readonly BillingAuditLogger $audit,
    ) {
    }

    /**
     * @param iterable<int, SubscriptionItem> $items
     */
    public function createForSubscription(
        Subscription $subscription,
        iterable $items,
        ?CarbonImmutable $dueAt = null,
        array $metadata = [],
    ): PlatformInvoice {
        $items = collect($items)->values();

        if ($items->isEmpty()) {
            throw new DomainException('An invoice requires at least one subscription item.');
        }

        foreach ($items as $item) {
            if (! $item instanceof SubscriptionItem || $item->subscription_id !== $subscription->getKey()) {
                throw new DomainException('All invoice items must belong to the same subscription.');
            }
        }

        $currency = strtoupper($subscription->currency);
        $subtotal = 0;
        $discount = 0;
        $tax = 0;
        $total = 0;

        foreach ($items as $item) {
            if (strtoupper($item->currency) !== $currency) {
                throw new DomainException('Subscription item currencies must match the subscription currency.');
            }

            $lineSubtotal = BillingMoney::multiply(
                $item->unit_amount_minor,
                $item->quantity,
                $currency,
            );

            $subtotal = $this->addMoney($subtotal, $lineSubtotal, 'Invoice subtotal');
            $discount = $this->addMoney($discount, $item->discount_amount_minor, 'Invoice discount');
            $tax = $this->addMoney($tax, $item->tax_amount_minor, 'Invoice tax');
            $total = $this->addMoney($total, $item->line_total_minor, 'Invoice total');
        }

        return $subscription->getConnection()->transaction(function () use (
            $subscription,
            $items,
            $dueAt,
            $metadata,
            $currency,
            $subtotal,
            $discount,
            $tax,
            $total,
        ): PlatformInvoice {
            $number = 'VP-'.now()->format('Ym').'-'.strtoupper(substr((string) Str::ulid(), -12));

            $invoice = PlatformInvoice::query()->create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->getKey(),
                'number' => $number,
                'status' => InvoiceStatus::Open,
                'currency' => $currency,
                'subtotal_minor' => $subtotal,
                'discount_minor' => $discount,
                'tax_minor' => $tax,
                'total_minor' => $total,
                'issued_at' => CarbonImmutable::now(),
                'due_at' => $dueAt,
                'metadata' => $metadata ?: null,
            ]);

            foreach ($items as $item) {
                PlatformInvoiceItem::query()->create([
                    'invoice_id' => $invoice->getKey(),
                    'subscription_item_id' => $item->getKey(),
                    'description' => $item->catalog_key,
                    'catalog_type' => $item->catalog_type,
                    'catalog_key' => $item->catalog_key,
                    'quantity' => $item->quantity,
                    'unit_amount_minor' => $item->unit_amount_minor,
                    'discount_minor' => $item->discount_amount_minor,
                    'tax_minor' => $item->tax_amount_minor,
                    'line_total_minor' => $item->line_total_minor,
                    'metadata' => $item->metadata,
                ]);
            }

            $this->audit->record($subscription->tenant, 'invoice.created', $invoice, [
                'total_minor' => $total,
                'currency' => $currency,
            ]);

            return $invoice->fresh('items');
        });
    }

    public function void(PlatformInvoice $invoice, string $reason): PlatformInvoice
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw new DomainException('A paid invoice cannot be voided.');
        }

        $invoice->update([
            'status' => InvoiceStatus::Void,
            'voided_at' => CarbonImmutable::now(),
            'metadata' => array_merge($invoice->metadata ?? [], ['void_reason' => $reason]),
        ]);

        $this->audit->record($invoice->tenant, 'invoice.voided', $invoice, ['reason' => $reason]);

        return $invoice->refresh();
    }

    public function markPaid(PlatformInvoice $invoice, CarbonImmutable $paidAt): PlatformInvoice
    {
        $invoice = PlatformInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

        if ($invoice->status === InvoiceStatus::Paid) {
            return $invoice;
        }

        if ($invoice->status !== InvoiceStatus::Open) {
            throw new DomainException('Only an open invoice can be marked paid.');
        }

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => $paidAt,
        ]);

        $this->audit->record($invoice->tenant, 'invoice.paid', $invoice);

        return $invoice->refresh();
    }
    private function addMoney(int $current, int $increment, string $label): int
    {
        if ($increment < 0 || $current > PHP_INT_MAX - $increment) {
            throw new DomainException("{$label} exceeds the supported integer range.");
        }

        return $current + $increment;
    }

}