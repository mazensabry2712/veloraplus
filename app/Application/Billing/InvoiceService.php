<?php

namespace App\Application\Billing;

use App\Domain\Billing\InvoiceStatus;
use App\Models\PlatformInvoice;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Illuminate\Support\Str;
use RuntimeException;

final class InvoiceService
{
    public function __construct(
        private readonly BillingAuditService $audit,
    ) {
    }

    /**
     * @param array<int, string> $itemStatuses
     */
    public function createForSubscription(
        Subscription $subscription,
        array $itemStatuses = ['pending'],
        ?string $dueAt = null,
    ): PlatformInvoice {
        $subscription->loadMissing('tenant');

        $items = $subscription->items()
            ->whereIn('status', $itemStatuses)
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            throw new RuntimeException('Cannot create an invoice without billable subscription items.');
        }

        $invoice = PlatformInvoice::query()->create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->getKey(),
            'number' => 'VLP-'.strtoupper((string) Str::ulid()),
            'status' => InvoiceStatus::Open,
            'currency' => $subscription->currency,
            'subtotal_minor' => $items->sum(fn (SubscriptionItem $item): int => $item->unit_amount_minor * $item->quantity),
            'discount_minor' => $items->sum(fn (SubscriptionItem $item): int => $item->discount_amount_minor),
            'tax_minor' => $items->sum(fn (SubscriptionItem $item): int => $item->tax_amount_minor),
            'credit_minor' => 0,
            'total_minor' => $items->sum(fn (SubscriptionItem $item): int => $item->total_amount_minor),
            'issued_at' => now()->toImmutable(),
            'due_at' => $dueAt ? \Carbon\CarbonImmutable::parse($dueAt) : now()->toImmutable(),
            'paid_at' => null,
            'metadata' => ['billing_cycle' => $subscription->billing_cycle->value],
        ]);

        foreach ($items as $item) {
            $invoice->items()->create([
                'subscription_item_id' => $item->getKey(),
                'description' => $this->description($item),
                'catalog_type' => $item->catalog_type,
                'catalog_key' => $item->catalog_key,
                'quantity' => $item->quantity,
                'unit_amount_minor' => $item->unit_amount_minor,
                'discount_minor' => $item->discount_amount_minor,
                'tax_minor' => $item->tax_amount_minor,
                'line_total_minor' => $item->total_amount_minor,
                'metadata' => ['catalog_price_id' => $item->catalog_price_id],
            ]);
        }

        $this->audit->record($subscription->tenant, 'invoice.created', $invoice, null, $invoice->status->value);

        return $invoice->load('items');
    }

    public function markPaid(PlatformInvoice $invoice): PlatformInvoice
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            return $invoice;
        }

        $from = $invoice->status->value;

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => now()->toImmutable(),
        ]);

        $invoice = $invoice->refresh();

        $this->audit->record(
            $invoice->tenant,
            'invoice.paid',
            $invoice,
            $from,
            InvoiceStatus::Paid->value,
        );

        return $invoice;
    }

    private function description(SubscriptionItem $item): string
    {
        return trim(ucwords(str_replace(['.', '_', '-'], ' ', $item->catalog_key ?? $item->item_type)));
    }
}