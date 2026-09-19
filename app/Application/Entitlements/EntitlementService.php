<?php

namespace App\Application\Entitlements;

use App\Application\Catalog\CatalogDependencyResolver;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Entitlements\EntitlementStatus;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantEntitlement;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

final class EntitlementService
{
    public function __construct(
        private readonly CatalogDependencyResolver $dependencies,
        private readonly DatabaseManager $database,
    ) {
    }

    public function grant(Tenant $tenant, Module|Feature $item, array $attributes = []): TenantEntitlement
    {
        $this->assertGrantable($item);

        $source = $this->source($attributes['source'] ?? EntitlementSource::Manual);
        $startsAt = $this->date($attributes['starts_at'] ?? null) ?? CarbonImmutable::now();
        $endsAt = $this->date($attributes['ends_at'] ?? null);
        $quantity = isset($attributes['quantity']) ? (int) $attributes['quantity'] : null;
        $metadata = $attributes['metadata'] ?? null;

        if ($endsAt !== null && $endsAt <= $startsAt) {
            throw new DomainException('Entitlement ends_at must be later than starts_at.');
        }

        return $this->database->connection('central')->transaction(function () use (
            $tenant,
            $item,
            $source,
            $startsAt,
            $endsAt,
            $quantity,
            $metadata,
        ): TenantEntitlement {
            $root = null;

            foreach ($this->dependencies->resolve([$item]) as $candidate) {
                if ($candidate instanceof Module && $candidate->is_core) {
                    continue;
                }

                if ($candidate instanceof Feature) {
                    $candidate->loadMissing('module');

                    if ($candidate->module?->is_core) {
                        continue;
                    }
                }

                $this->assertGrantable($candidate);

                $isRoot = $candidate->is($item);
                $candidateSource = $isRoot ? $source : EntitlementSource::Dependency;

                $entitlement = $this->upsert(
                    $tenant,
                    $candidate,
                    $candidateSource,
                    $isRoot ? $attributes['source_reference'] ?? null : $item->getKey(),
                    $startsAt,
                    $endsAt,
                    $quantity,
                    $metadata,
                );

                if ($isRoot) {
                    $root = $entitlement;
                }
            }

            if ($root === null) {
                throw new DomainException('The requested catalog item cannot receive a tenant entitlement.');
            }

            return $root->refresh();
        });
    }

    public function rebuildProjection(Tenant $tenant, array $grants): void
    {
        $desired = [];

        foreach ($grants as $grant) {
            $item = $grant['item'] ?? null;

            if (! $item instanceof Module && ! $item instanceof Feature) {
                throw new DomainException('Each entitlement projection grant must contain a Module or Feature item.');
            }

            $this->assertGrantable($item);

            $source = $this->source($grant['source'] ?? EntitlementSource::Manual);
            $startsAt = $this->date($grant['starts_at'] ?? null);
            $endsAt = $this->date($grant['ends_at'] ?? null);
            $status = EntitlementStatus::from(
                strtolower((string) ($grant['status'] ?? EntitlementStatus::Active->value)),
            );

            if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
                throw new DomainException('Entitlement ends_at must be later than starts_at.');
            }

            $resolved = $this->dependencies->resolve([$item]);

            foreach ($resolved as $candidate) {
                if ($candidate instanceof Module && $candidate->is_core) {
                    continue;
                }

                if ($candidate instanceof Feature) {
                    $candidate->loadMissing('module');

                    if ($candidate->module?->is_core) {
                        continue;
                    }
                }

                $this->assertGrantable($candidate);

                $isRoot = $candidate->is($item);
                $candidateSource = $isRoot ? $source : EntitlementSource::Dependency;
                $key = $this->nodeKey($candidate);

                $payload = [
                    'tenant_id' => $tenant->getKey(),
                    'catalog_type' => $candidate->getMorphClass(),
                    'catalog_key' => $candidate->key,
                    'status' => $status->value,
                    'source' => $candidateSource->value,
                    'source_reference' => $isRoot
                        ? $grant['source_reference'] ?? null
                        : $item->getKey(),
                    'quantity' => isset($grant['quantity']) ? (int) $grant['quantity'] : null,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'metadata' => $grant['metadata'] ?? null,
                ];

                $current = $desired[$key] ?? null;

                if ($current === null || $candidateSource->priority() >= EntitlementSource::from($current['source'])->priority()) {
                    $desired[$key] = $payload;
                }
            }
        }

        $this->database->connection('central')->transaction(function () use ($tenant, $desired): void {
            TenantEntitlement::query()
                ->where('tenant_id', $tenant->getKey())
                ->delete();

            foreach ($desired as $payload) {
                TenantEntitlement::query()->create($payload);
            }
        });
    }

    public function scheduleRemoval(
        Tenant $tenant,
        Module|Feature $item,
        CarbonImmutable $endsAt,
    ): TenantEntitlement {
        $this->assertCatalogItemExists($item);

        $entitlement = $this->findDirect($tenant, $item);

        if ($entitlement === null || ! $this->isEffective($entitlement, CarbonImmutable::now())) {
            throw new DomainException('Only an active entitlement can be scheduled for removal.');
        }

        $now = CarbonImmutable::now();

        if ($endsAt <= $now) {
            throw new DomainException('Scheduled entitlement removal must be in the future.');
        }

        $entitlement->update([
            'status' => EntitlementStatus::ScheduledForRemoval,
            'ends_at' => $endsAt,
        ]);

        return $entitlement->refresh();
    }

    public function revoke(Tenant $tenant, Module|Feature $item): TenantEntitlement
    {
        $this->assertCatalogItemExists($item);

        $entitlement = $this->findDirect($tenant, $item);

        if ($entitlement === null) {
            throw new DomainException('Tenant entitlement does not exist.');
        }

        $entitlement->update([
            'status' => EntitlementStatus::Inactive,
            'ends_at' => CarbonImmutable::now(),
        ]);

        return $entitlement->refresh();
    }

    public function hasModule(Tenant $tenant, string $key, ?CarbonImmutable $at = null): bool
    {
        $module = Module::query()->where('key', strtolower(trim($key)))->first();

        if ($module === null) {
            return false;
        }

        return $this->hasItem($tenant, $module, $at);
    }

    public function hasFeature(Tenant $tenant, string $key, ?CarbonImmutable $at = null): bool
    {
        $feature = Feature::query()->where('key', strtolower(trim($key)))->first();

        if ($feature === null) {
            return false;
        }

        return $this->hasItem($tenant, $feature, $at);
    }

    public function canUse(Tenant $tenant, string $capability, ?CarbonImmutable $at = null): bool
    {
        $capability = strtolower(trim($capability));

        if (str_starts_with($capability, 'module:')) {
            return $this->hasModule($tenant, substr($capability, 7), $at);
        }

        if (str_starts_with($capability, 'feature:')) {
            return $this->hasFeature($tenant, substr($capability, 8), $at);
        }

        $feature = Feature::query()->where('key', $capability)->first();

        if ($feature !== null) {
            return $this->hasFeature($tenant, $capability, $at);
        }

        return $this->hasModule($tenant, $capability, $at);
    }

    public function status(Tenant $tenant, string $key, ?CarbonImmutable $at = null): ?EntitlementStatus
    {
        $module = Module::query()->where('key', strtolower(trim($key)))->first();

        if ($module !== null) {
            if ($module->is_core) {
                return EntitlementStatus::Active;
            }

            return $this->effectiveStatus($this->findDirect($tenant, $module), $at ?? CarbonImmutable::now());
        }

        $feature = Feature::query()->where('key', strtolower(trim($key)))->first();

        if ($feature === null) {
            return null;
        }

        if ($this->isImplicitlyAvailable($feature)) {
            return EntitlementStatus::Active;
        }

        $at ??= CarbonImmutable::now();

        $direct = $this->findDirect($tenant, $feature);
        $status = $this->effectiveStatus($direct, $at);

        if ($status !== null) {
            return $status;
        }

        $moduleEntitlement = $this->findDirect($tenant, $feature->module()->firstOrFail());

        return $this->effectiveStatus($moduleEntitlement, $at);
    }

    public function limit(Tenant $tenant, string $key, ?CarbonImmutable $at = null): ?int
    {
        $at ??= CarbonImmutable::now();

        $feature = Feature::query()->where('key', strtolower(trim($key)))->first();

        if ($feature !== null) {
            $direct = $this->findDirect($tenant, $feature);

            if ($direct !== null && $this->isEffective($direct, $at)) {
                return $direct->quantity;
            }

            $moduleEntitlement = $this->findDirect($tenant, $feature->module()->firstOrFail());

            return $moduleEntitlement !== null && $this->isEffective($moduleEntitlement, $at)
                ? $moduleEntitlement->quantity
                : null;
        }

        $module = Module::query()->where('key', strtolower(trim($key)))->first();

        if ($module === null) {
            return null;
        }

        $entitlement = $this->findDirect($tenant, $module);

        return $entitlement !== null && $this->isEffective($entitlement, $at)
            ? $entitlement->quantity
            : null;
    }

    /**
     * @return Collection<int, Module|Feature>
     */
    public function resolveDependencies(array $items): Collection
    {
        return $this->dependencies->resolve($items);
    }

    private function hasItem(Tenant $tenant, Module|Feature $item, ?CarbonImmutable $at = null): bool
    {
        $at ??= CarbonImmutable::now();

        if ($this->isImplicitlyAvailable($item)) {
            return true;
        }

        if (! $this->isCatalogItemActive($item)) {
            return false;
        }

        $baseAvailable = $this->baseAccess($tenant, $item, $at);

        if (! $baseAvailable) {
            return false;
        }

        foreach ($this->dependencies->resolve([$item]) as $dependency) {
            if ($dependency->is($item)) {
                continue;
            }

            if ($this->isImplicitlyAvailable($dependency)) {
                continue;
            }

            if (! $this->isCatalogItemActive($dependency) || ! $this->baseAccess($tenant, $dependency, $at)) {
                return false;
            }
        }

        return true;
    }

    private function baseAccess(Tenant $tenant, Module|Feature $item, CarbonImmutable $at): bool
    {
        $direct = $this->findDirect($tenant, $item);

        if ($direct !== null && $this->isEffective($direct, $at)) {
            return true;
        }

        if ($item instanceof Feature) {
            $item->loadMissing('module');
            $moduleEntitlement = $this->findDirect($tenant, $item->module);

            return $moduleEntitlement !== null && $this->isEffective($moduleEntitlement, $at);
        }

        return false;
    }

    private function findDirect(Tenant $tenant, Module|Feature $item): ?TenantEntitlement
    {
        return TenantEntitlement::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('catalog_type', $item->getMorphClass())
            ->where('catalog_key', $item->key)
            ->first();
    }

    private function effectiveStatus(
        ?TenantEntitlement $entitlement,
        CarbonImmutable $at,
    ): ?EntitlementStatus {
        if ($entitlement === null) {
            return null;
        }

        if (! $this->isEffective($entitlement, $at)) {
            return EntitlementStatus::Inactive;
        }

        return $entitlement->status;
    }

    private function isEffective(TenantEntitlement $entitlement, CarbonImmutable $at): bool
    {
        return $entitlement->status->grantsAccess()
            && ($entitlement->starts_at === null || $entitlement->starts_at <= $at)
            && ($entitlement->ends_at === null || $entitlement->ends_at > $at);
    }

    private function assertGrantable(Module|Feature $item): void
    {
        $this->assertCatalogItemExists($item);

        if ($item instanceof Module && $item->is_core) {
            throw new DomainException('Core modules do not use tenant entitlements.');
        }

        if ($item instanceof Feature) {
            $item->loadMissing('module');

            if ($item->module === null || $item->module->is_core) {
                throw new DomainException('Features under the core module do not use tenant entitlements.');

            }
        }

        if ($item->status !== 'active') {
            throw new DomainException('Only active catalog items can receive tenant entitlements.');
        }

        if ($item instanceof Feature && $item->module->status !== 'active') {
            throw new DomainException('A Feature cannot be entitled while its Module is not active.');
        }
    }

    private function assertCatalogItemExists(Module|Feature $item): void
    {
        if ($item->getKey() === null || $item->key === null || $item->key === '') {
            throw new DomainException('A persisted catalog item with a stable key is required.');
        }
    }

    private function isCatalogItemActive(Module|Feature $item): bool
    {
        return $item->status === 'active'
            && (! $item instanceof Feature || $item->module()->firstOrFail()->status === 'active');
    }

    private function isImplicitlyAvailable(Module|Feature $item): bool
    {
        if ($item instanceof Module) {
            return $item->is_core;
        }

        $item->loadMissing('module');

        return $item->module?->is_core === true;
    }

    private function upsert(
        Tenant $tenant,
        Module|Feature $item,
        EntitlementSource $source,
        ?string $sourceReference,
        CarbonImmutable $startsAt,
        ?CarbonImmutable $endsAt,
        ?int $quantity,
        mixed $metadata,
    ): TenantEntitlement {
        $existing = $this->findDirect($tenant, $item);

        if ($existing === null) {
            return TenantEntitlement::query()->create([
                'tenant_id' => $tenant->getKey(),
                'catalog_type' => $item->getMorphClass(),
                'catalog_key' => $item->key,
                'status' => EntitlementStatus::Active,
                'source' => $source,
                'source_reference' => $sourceReference,
                'quantity' => $quantity,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'metadata' => $metadata,
            ]);
        }

        $existingSource = $existing->source;

        $existing->update([
            'status' => EntitlementStatus::Active,
            'source' => $source->priority() >= $existingSource->priority() ? $source : $existingSource,
            'source_reference' => $source->priority() >= $existingSource->priority()
                ? $sourceReference
                : $existing->source_reference,
            'quantity' => $source->priority() >= $existingSource->priority()
                ? $quantity
                : $existing->quantity,
            'starts_at' => $source->priority() >= $existingSource->priority()
                ? $startsAt
                : $existing->starts_at,
            'ends_at' => $source->priority() >= $existingSource->priority()
                ? $endsAt
                : $existing->ends_at,
            'metadata' => $source->priority() >= $existingSource->priority()
                ? $metadata
                : $existing->metadata,
        ]);

        return $existing->refresh();
    }

    private function source(EntitlementSource|string $source): EntitlementSource
    {
        return $source instanceof EntitlementSource
            ? $source
            : EntitlementSource::from(strtolower(trim($source)));
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse($value);
    }

    private function nodeKey(Module|Feature $item): string
    {
        return "{$item->getMorphClass()}:{$item->getKey()}";
    }
}
