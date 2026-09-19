<?php

namespace App\Application\Catalog;

use App\Domain\Catalog\CatalogStatus;
use App\Models\Bundle;
use App\Models\CatalogDependency;
use App\Models\CatalogPrice;
use App\Models\Feature;
use App\Models\Module;
use DomainException;

final class CatalogManager
{
    public function __construct(
        private readonly CatalogDependencyManager $dependencies,
        private readonly CatalogPriceManager $prices,
    ) {
    }

    public function createModule(array $attributes): Module
    {
        $key = strtolower(trim((string) ($attributes['key'] ?? '')));
        CatalogStatus::assertKey($key);
        $status = strtolower((string) ($attributes['status'] ?? 'draft'));
        CatalogStatus::assertOneOf($status, CatalogStatus::MODULES, 'module status');

        return Module::query()->create([
            'key' => $key,
            'name' => (string) ($attributes['name'] ?? $key),
            'description' => $attributes['description'] ?? null,
            'status' => $status,
            'is_core' => (bool) ($attributes['is_core'] ?? false),
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    public function createFeature(Module $module, array $attributes): Feature
    {
        $key = strtolower(trim((string) ($attributes['key'] ?? '')));
        CatalogStatus::assertKey($key);
        $status = strtolower((string) ($attributes['status'] ?? 'draft'));
        $billingMode = strtolower((string) ($attributes['billing_mode'] ?? 'flat'));
        CatalogStatus::assertOneOf($status, CatalogStatus::FEATURES, 'feature status');
        CatalogStatus::assertOneOf($billingMode, CatalogStatus::BILLING_MODES, 'billing mode');

        return $module->features()->create([
            'key' => $key,
            'name' => (string) ($attributes['name'] ?? $key),
            'description' => $attributes['description'] ?? null,
            'status' => $status,
            'billing_mode' => $billingMode,
            'is_required' => (bool) ($attributes['is_required'] ?? false),
            'is_individually_purchasable' => (bool) ($attributes['is_individually_purchasable'] ?? true),
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    public function createBundle(array $attributes): Bundle
    {
        $key = strtolower(trim((string) ($attributes['key'] ?? '')));
        CatalogStatus::assertKey($key);
        $status = strtolower((string) ($attributes['status'] ?? 'draft'));
        $discountBps = (int) ($attributes['discount_bps'] ?? 0);

        CatalogStatus::assertOneOf($status, CatalogStatus::BUNDLES, 'bundle status');

        if ($discountBps < 0 || $discountBps > 10000) {
            throw new DomainException('Bundle discount_bps must be between 0 and 10000.');
        }

        return Bundle::query()->create([
            'key' => $key,
            'name' => (string) ($attributes['name'] ?? $key),
            'description' => $attributes['description'] ?? null,
            'status' => $status,
            'discount_bps' => $discountBps,
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    public function setModuleStatus(Module $module, string $status): Module
    {
        $status = strtolower($status);
        CatalogStatus::assertOneOf($status, CatalogStatus::MODULES, 'module status');
        $module->update(['status' => $status]);

        return $module->refresh();
    }

    public function setFeatureStatus(Feature $feature, string $status): Feature
    {
        $status = strtolower($status);
        CatalogStatus::assertOneOf($status, CatalogStatus::FEATURES, 'feature status');
        $feature->update(['status' => $status]);

        return $feature->refresh();
    }

    public function setBundleStatus(Bundle $bundle, string $status): Bundle
    {
        $status = strtolower($status);
        CatalogStatus::assertOneOf($status, CatalogStatus::BUNDLES, 'bundle status');
        $bundle->update(['status' => $status]);

        return $bundle->refresh();
    }

    public function addDependency(Module|Feature $dependent, Module|Feature $dependency): CatalogDependency
    {
        return $this->dependencies->add($dependent, $dependency);
    }

    public function addModuleToBundle(Bundle $bundle, Module $module): void
    {
        $bundle->modules()->syncWithoutDetaching([$module->getKey()]);
    }

    public function addFeatureToBundle(Bundle $bundle, Feature $feature): void
    {
        $bundle->features()->syncWithoutDetaching([$feature->getKey()]);
    }

    public function addPrice(Module|Feature|Bundle $priceable, array $attributes): CatalogPrice
    {
        return $this->prices->add($priceable, $attributes);
    }
}
