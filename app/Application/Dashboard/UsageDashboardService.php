<?php

namespace App\Application\Dashboard;

use App\Application\Entitlements\EntitlementService;
use App\Domain\Tenancy\TenantContext;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantEntitlement;
use Illuminate\Support\Collection;
use RuntimeException;

final class UsageDashboardService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly EntitlementService $entitlements,
    ) {
    }

    /**
     * @return array{tenant: Tenant, limits: Collection<int, array<string, mixed>>}
     */
    public function overview(): array
    {
        $tenant = $this->context->current();

        if ($tenant === null) {
            throw new RuntimeException('Usage dashboard requires an active tenant context.');
        }

        $rows = TenantEntitlement::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('status', ['active', 'scheduled_for_removal', 'pending'])
            ->get(['catalog_type', 'catalog_key', 'status', 'source', 'quantity', 'starts_at', 'ends_at']);

        $modules = Module::query()->whereIn('key', $rows->where('catalog_type', 'module')->pluck('catalog_key'))->get()->keyBy('key');
        $features = Feature::query()->with('module')->whereIn('key', $rows->where('catalog_type', 'feature')->pluck('catalog_key'))->get()->keyBy('key');

        $limits = $rows->map(function (TenantEntitlement $entitlement) use ($tenant, $modules, $features): array {
            $item = $entitlement->catalog_type === 'module'
                ? $modules->get($entitlement->catalog_key)
                : $features->get($entitlement->catalog_key);

            $key = $item?->key ?? $entitlement->catalog_key;
            $name = $item?->name ?? $key;

            return [
                'type' => $entitlement->catalog_type,
                'key' => $key,
                'name' => $name,
                'module' => $item instanceof Feature ? $item->module?->name : null,
                'status' => $entitlement->status->value,
                'source' => $entitlement->source->value,
                'limit' => $this->entitlements->limit($tenant, $key),
                'starts_at' => $entitlement->starts_at,
                'ends_at' => $entitlement->ends_at,
                'usage' => null,
            ];
        })->values();

        return [
            'tenant' => $tenant,
            'limits' => $limits,
        ];
    }
}
