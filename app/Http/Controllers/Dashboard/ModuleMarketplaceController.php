<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Catalog\ModuleMarketplaceService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\PurchaseMarketplaceItemRequest;
use App\Models\Subscription;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class ModuleMarketplaceController extends Controller
{
    public function index(
        ModuleMarketplaceService $marketplace,
        TenantContext $context,
    ): View|RedirectResponse {
        $tenant = $context->current();

        if ($tenant === null) {
            return redirect()->route('dashboard')
                ->withErrors(['marketplace' => 'Tenant marketplace context is required.']);
        }

        return view('dashboard.marketplace', [
            'tenant' => $tenant,
            'catalog' => $marketplace->overview($tenant),
        ]);
    }

    public function preview(
        PurchaseMarketplaceItemRequest $request,
        ModuleMarketplaceService $marketplace,
        TenantContext $context,
    ): JsonResponse {
        Gate::authorize('manage', Subscription::class);

        $tenant = $context->current();

        if ($tenant === null) {
            return response()->json(['message' => 'Marketplace tenant context is required.'], 422);
        }

        try {
            $data = $request->validated();

            return response()->json($marketplace->previewPurchase(
                tenant: $tenant,
                catalogType: (string) $data['catalog_type'],
                catalogKey: (string) $data['catalog_key'],
                quantity: (int) $data['quantity'],
            ));
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function purchase(
        PurchaseMarketplaceItemRequest $request,
        ModuleMarketplaceService $marketplace,
        TenantContext $context,
    ): RedirectResponse {
        Gate::authorize('manage', Subscription::class);

        $tenant = $context->current();

        if ($tenant === null) {
            return back()->withErrors(['marketplace' => 'Tenant marketplace context is required.']);
        }

        try {
            $data = $request->validated();

            if (! isset($data['quote_token']) || $data['quote_token'] === '') {
                throw new DomainException('Preview the current quote before confirming your Marketplace purchase.');
            }

            $currentQuote = $marketplace->previewPurchase(
                tenant: $tenant,
                catalogType: (string) $data['catalog_type'],
                catalogKey: (string) $data['catalog_key'],
                quantity: (int) $data['quantity'],
            );

            if (! hash_equals((string) $currentQuote['confirmation_token'], (string) $data['quote_token'])) {
                throw new DomainException('The Marketplace price or required items have changed. Review the refreshed quote before confirming.');
            }

            $invoice = $marketplace->requestPurchase(
                tenant: $tenant,
                catalogType: (string) $data['catalog_type'],
                catalogKey: (string) $data['catalog_key'],
                quantity: (int) $data['quantity'],
                quoteToken: (string) $data['quote_token'],
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['marketplace' => $exception->getMessage()])->withInput();
        }

        if ($invoice->total_minor === 0 || $invoice->status->value === 'paid') {
            return to_route('dashboard.marketplace.index')
                ->with('status', 'The free Marketplace selection has been activated successfully.');
        }

        return to_route('dashboard.marketplace.index')
            ->with('status', 'Marketplace purchase invoice created successfully.')
            ->with('marketplace_upgrade_invoice_id', $invoice->getKey());
    }
}
