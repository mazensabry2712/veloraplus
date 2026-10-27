@extends('layouts.dashboard')

@section('title', 'Module Marketplace')
@section('heading', 'Module Marketplace')

@section('content')
    @php
        $subscriptionStatus = $catalog['subscription']?->status?->value ?? '';
        $canPurchase = in_array($subscriptionStatus, ['active', 'grace'], true);
        $groups = [
            ['id' => 'modules', 'title' => 'Business apps', 'description' => 'Complete capabilities that bring related features together.', 'items' => $catalog['modules']],
            ['id' => 'features', 'title' => 'Individual features', 'description' => 'Add a specific capability to your existing workspace.', 'items' => $catalog['features']],
            ['id' => 'bundles', 'title' => 'Bundles', 'description' => 'Related capabilities packaged together at one transparent price.', 'items' => $catalog['bundles']],
        ];
    @endphp

    @if (session('status'))
        <div role="status" class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if (session('marketplace_upgrade_invoice_id'))
        <section class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 p-5">
            <p class="font-semibold text-indigo-950">Your purchase invoice is ready</p>
            <p class="mt-1 text-sm text-indigo-800">Review the invoice and complete payment to activate the selected capabilities.</p>
            <form method="POST" action="{{ route('dashboard.billing.invoices.checkout', session('marketplace_upgrade_invoice_id')) }}" class="mt-4">
                @csrf
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Continue to secure checkout
                </button>
            </form>
        </section>
    @endif

    @if (session('errors')?->has('marketplace'))
        <div role="alert" class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ session('errors')->first('marketplace') }}
        </div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-indigo-600">Grow by outcomes</p>
        <h3 class="mt-2 max-w-3xl text-2xl font-semibold tracking-tight text-slate-950">Add the capabilities your business needs — without paying twice for dependencies.</h3>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
            Browse connected apps, individual features, and bundles. Before you commit, preview the full price,
            required dependencies, discounts, and tax. Nothing is added to your subscription until you confirm.
        </p>
        <div class="mt-5 flex flex-wrap gap-2 text-xs font-medium text-slate-600">
            <span class="rounded-full bg-slate-100 px-3 py-1.5">Shared dependencies are deduplicated</span>
            <span class="rounded-full bg-slate-100 px-3 py-1.5">Transparent line-item pricing</span>
            <span class="rounded-full bg-slate-100 px-3 py-1.5">Preview is read-only</span>
        </div>
    </section>

    @if (! $canPurchase)
        <section class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            @if ($subscriptionStatus === 'pending_payment')
                Your subscription is awaiting payment. Complete the current invoice before adding capabilities.
            @else
                An active subscription is required before you can request a Marketplace quote or purchase an add-on.
            @endif
        </section>
    @endif

    <div class="mt-8 space-y-10">
        @foreach ($groups as $group)
            <section id="{{ $group['id'] }}">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-950">{{ $group['title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $group['description'] }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($group['items'] as $item)
                        <article data-item-card data-type="{{ $item['catalog_type'] }}" data-key="{{ $item['catalog_key'] }}"
                                 class="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="font-semibold text-slate-950">{{ $item['name'] }}</h4>
                                    <p class="mt-1 break-all text-xs text-slate-400">{{ $item['catalog_key'] }}</p>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium
                                    {{ $item['state'] === 'active' ? 'bg-emerald-50 text-emerald-700' : ($item['state'] === 'available' ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-800') }}">
                                    {{ str_replace('_', ' ', ucfirst($item['state'])) }}
                                </span>
                            </div>

                            @if (! empty($item['description']))
                                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $item['description'] }}</p>
                            @else
                                <p class="mt-3 text-sm leading-6 text-slate-500">A connected capability in your VeloraPlus workspace.</p>
                            @endif

                            <div class="mt-5 flex-1">
                                @if ($item['amount_minor'] !== null)
                                    <p class="text-xl font-semibold text-slate-950">
                                        {{ number_format($item['amount_minor'] / 100, 2) }}
                                        <span class="text-sm font-medium text-slate-500">{{ $item['currency'] }}</span>
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">per {{ $item['billing_cycle'] ?? 'billing cycle' }}, before dependency adjustments</p>
                                @else
                                    <p class="text-sm font-medium text-slate-500">Price unavailable for this currency or billing cycle.</p>
                                @endif
                            </div>

                            @if ($item['state'] === 'available' && $item['amount_minor'] !== null)
                                @if ($canPurchase)
                                    @can('billing.manage')
                                        <button type="button" data-quote-button
                                                class="mt-5 w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                                            Preview full quote
                                        </button>
                                    @else
                                        <p class="mt-5 rounded-lg bg-slate-50 px-3 py-2 text-center text-xs text-slate-500">Ask a billing manager to request a quote.</p>
                                    @endcan
                                @else
                                    <button type="button" disabled class="mt-5 w-full cursor-not-allowed rounded-lg bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-400">
                                        Subscription required
                                    </button>
                                @endif
                            @elseif ($item['state'] !== 'available')
                                <p class="mt-5 rounded-lg bg-slate-50 px-3 py-2 text-center text-xs text-slate-500">
                                    {{ $item['state'] === 'active' ? 'Already included in your workspace.' : 'Not available for purchase in its current state.' }}
                                </p>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-500">
                            No active items are available in this category yet.
                        </div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    <section id="marketplace-quote" hidden class="mt-10 rounded-2xl border border-indigo-200 bg-white p-5 shadow-sm sm:p-7">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Quote preview</p>
                <h3 class="mt-1 text-xl font-semibold text-slate-950">Review the complete cost</h3>
                <p class="mt-1 text-sm text-slate-600">This preview does not create an invoice or change your entitlements.</p>
            </div>
            <button type="button" id="close-marketplace-quote" class="self-start rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">Close</button>
        </div>

        <form id="marketplace-preview-form" class="mt-5 flex flex-col gap-3 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-end">
            <input type="hidden" name="catalog_type">
            <input type="hidden" name="catalog_key">
            <label class="min-w-0 flex-1 text-sm font-medium text-slate-700">
                Selected capability
                <p id="quote-selection-name" class="mt-1 break-all font-normal text-slate-500">—</p>
            </label>
            <label class="w-full text-sm font-medium text-slate-700 sm:w-32">
                Quantity
                <input type="number" name="quantity" min="1" max="1000" value="1" required
                       class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
            </label>
            <button type="submit" id="refresh-marketplace-quote"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                Update quote
            </button>
        </form>

        <p id="marketplace-quote-error" role="alert" hidden class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"></p>
        <p id="marketplace-quote-loading" hidden class="mt-4 text-sm text-slate-500">Calculating the complete price…</p>

        <div id="marketplace-quote-output" hidden class="mt-5">
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Line item</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Line total</th>
                        </tr>
                    </thead>
                    <tbody id="marketplace-quote-lines" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>

            <dl class="ml-auto mt-5 max-w-sm space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Subtotal</dt><dd id="quote-subtotal" class="font-medium tabular-nums"></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Discount</dt><dd id="quote-discount" class="font-medium tabular-nums"></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Tax</dt><dd id="quote-tax" class="font-medium tabular-nums"></dd></div>
                <div class="flex justify-between gap-4 border-t border-slate-200 pt-3 text-base"><dt class="font-semibold text-slate-950">Total</dt><dd id="quote-total" class="font-semibold tabular-nums text-slate-950"></dd></div>
            </dl>

            <p id="quote-dependency-note" class="mt-4 text-sm text-slate-600"></p>

            @can('billing.manage')
                <form id="marketplace-purchase-form" method="POST" action="{{ route('dashboard.marketplace.purchase') }}" class="mt-6 flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    @csrf
                    <input type="hidden" name="catalog_type">
                    <input type="hidden" name="catalog_key">
                    <input type="hidden" name="quantity" value="1">
                    <p id="quote-confirmation-note" class="max-w-2xl text-xs leading-5 text-slate-500">Confirming creates the upgrade invoice. Paid capabilities become available only after payment is verified.</p>
                    <button type="submit" id="confirm-marketplace-purchase" disabled
                            class="shrink-0 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                        Confirm and create invoice
                    </button>
                </form>
            @endcan
        </div>
    </section>

    <script>
        (() => {
            const previewUrl = @json(route('dashboard.marketplace.preview'));
            const csrf = @json(csrf_token());
            const panel = document.getElementById('marketplace-quote');
            const previewForm = document.getElementById('marketplace-preview-form');
            const purchaseForm = document.getElementById('marketplace-purchase-form');
            const error = document.getElementById('marketplace-quote-error');
            const loading = document.getElementById('marketplace-quote-loading');
            const output = document.getElementById('marketplace-quote-output');
            const lines = document.getElementById('marketplace-quote-lines');
            const confirmButton = document.getElementById('confirm-marketplace-purchase');
            let selectionName = '';
            let lastQuotedSelection = null;
            let previewRequestId = 0;

            if (!previewForm) return;

            const money = (minor, currency) => new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: currency || 'EGP',
            }).format((Number(minor) || 0) / 100);

            const syncPurchaseFields = () => {
                if (!purchaseForm) return;
                purchaseForm.elements.catalog_type.value = previewForm.elements.catalog_type.value;
                purchaseForm.elements.catalog_key.value = previewForm.elements.catalog_key.value;
                purchaseForm.elements.quantity.value = previewForm.elements.quantity.value;
            };

            const addCell = (row, value, className = '') => {
                const cell = document.createElement('td');
                cell.className = className;
                cell.textContent = value;
                row.appendChild(cell);
            };

            const preview = async () => {
                const requestId = ++previewRequestId;
                error.hidden = true;
                output.hidden = true;
                loading.hidden = false;
                if (confirmButton) confirmButton.disabled = true;

                const data = {
                    catalog_type: previewForm.elements.catalog_type.value,
                    catalog_key: previewForm.elements.catalog_key.value,
                    quantity: Number(previewForm.elements.quantity.value),
                };

                try {
                    const response = await fetch(previewUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(data),
                    });
                    const quote = await response.json();
                    if (requestId !== previewRequestId) return;

                    if (!response.ok) {
                        throw new Error(quote.message || 'The quote could not be calculated.');
                    }

                    lines.replaceChildren();
                    (quote.lines || []).forEach(line => {
                        const row = document.createElement('tr');
                        addCell(row, line.description || line.catalog_key, 'px-4 py-3 text-slate-800');
                        addCell(row, String(line.quantity ?? 1), 'px-4 py-3 text-right text-slate-600');
                        addCell(row, money(line.line_total_minor, quote.currency), 'px-4 py-3 text-right font-medium tabular-nums');
                        lines.appendChild(row);
                    });

                    document.getElementById('quote-subtotal').textContent = money(quote.subtotal_minor, quote.currency);
                    document.getElementById('quote-discount').textContent = money(quote.discount_minor, quote.currency);
                    document.getElementById('quote-tax').textContent = money(quote.tax_minor, quote.currency);
                    document.getElementById('quote-total').textContent = money(quote.total_minor, quote.currency);

                    const dependencies = (quote.lines || []).filter(line =>
                        line.metadata && line.metadata.commercial_origin === 'required_dependency'
                    );
                    document.getElementById('quote-dependency-note').textContent = dependencies.length
                        ? 'Required dependencies are included in this quote as separate transparent line items. Shared dependencies are charged only once.'
                        : 'No additional billable dependencies were added to this selection.';

                    syncPurchaseFields();
                    lastQuotedSelection = {
                        catalog_type: data.catalog_type,
                        catalog_key: data.catalog_key,
                        quantity: data.quantity,
                        total_minor: Number(quote.total_minor),
                    };
                    output.hidden = false;
                    if (confirmButton) {
                        confirmButton.disabled = false;
                        confirmButton.textContent = Number(quote.total_minor) === 0
                            ? 'Activate free selection'
                            : 'Confirm and create invoice';
                    }
                    const confirmationNote = document.getElementById('quote-confirmation-note');
                    if (confirmationNote) {
                        confirmationNote.textContent = Number(quote.total_minor) === 0
                            ? 'This selection has no amount due. Confirming activates it immediately without a payment attempt.'
                            : 'Confirming creates the upgrade invoice. Paid capabilities become available only after payment is verified.';
                    }
                } catch (exception) {
                    error.textContent = exception.message || 'The quote could not be calculated.';
                    error.hidden = false;
                } finally {
                    if (requestId === previewRequestId) loading.hidden = true;
                }
            };

            document.querySelectorAll('[data-quote-button]').forEach(button => {
                button.addEventListener('click', () => {
                    const card = button.closest('[data-item-card]');
                    previewForm.elements.catalog_type.value = card.dataset.type;
                    previewForm.elements.catalog_key.value = card.dataset.key;
                    previewForm.elements.quantity.value = '1';
                    selectionName = card.querySelector('h4')?.textContent?.trim() || card.dataset.key;
                    document.getElementById('quote-selection-name').textContent = selectionName;
                    lastQuotedSelection = null;
                    syncPurchaseFields();
                    panel.hidden = false;
                    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    preview();
                });
            });

            previewForm.addEventListener('submit', event => {
                event.preventDefault();
                preview();
            });

            previewForm.elements.quantity.addEventListener('input', () => {
                previewRequestId += 1;
                lastQuotedSelection = null;
                output.hidden = true;
                loading.hidden = true;
                if (confirmButton) confirmButton.disabled = true;
            });

            document.getElementById('close-marketplace-quote')?.addEventListener('click', () => {
                panel.hidden = true;
            });

            purchaseForm?.addEventListener('submit', event => {
                const current = {
                    catalog_type: previewForm.elements.catalog_type.value,
                    catalog_key: previewForm.elements.catalog_key.value,
                    quantity: Number(previewForm.elements.quantity.value),
                };

                if (!lastQuotedSelection
                    || lastQuotedSelection.catalog_type !== current.catalog_type
                    || lastQuotedSelection.catalog_key !== current.catalog_key
                    || lastQuotedSelection.quantity !== current.quantity) {
                    event.preventDefault();
                    preview();
                    return;
                }

                if (!confirm(current.quantity > 1
                    ? 'Create an upgrade invoice for ' + current.quantity + ' units of this selection?'
                    : 'Create an upgrade invoice for this selection?')) {
                    event.preventDefault();
                    return;
                }

                const submitButton = purchaseForm.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Submitting purchase…';
                }
            });
        })();
    </script>
@endsection
