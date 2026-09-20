<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Catalog\ModuleMarketplaceService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\PurchaseMarketplaceItemRequest;
use App\Models\Subscription;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class ModuleMarketplaceController extends Controller
{
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

            $invoice = $marketplace->requestPurchase(
                tenant: $tenant,
                catalogType: (string) $data['catalog_type'],
                catalogKey: (string) $data['catalog_key'],
                quantity: (int) $data['quantity'],
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['marketplace' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')
            ->with('status', 'Marketplace purchase request created successfully.')
            ->with('marketplace_upgrade_invoice_id', $invoice->getKey());
    }
}
