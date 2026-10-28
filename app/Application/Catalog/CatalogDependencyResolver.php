<?php

namespace App\Application\Catalog;

use App\Models\Bundle;
use App\Models\CatalogDependency;
use App\Models\Feature;
use App\Models\Module;
use DomainException;
use Illuminate\Support\Collection;

final class CatalogDependencyResolver
{
    /**
     * @param array<int, Module|Feature> $items
     *
     * @return Collection<int, Module|Feature>
     */
    public function resolve(array $items): Collection
    {
        $roots = array_values(array_filter(
            $items,
            static fn (mixed $item): bool => $item instanceof Module || $item instanceof Feature,
        ));

        if ($roots === []) {
            return collect();
        }

        $adjacency = [];

        foreach (CatalogDependency::query()->with('dependency')->get() as $edge) {
            if (! $edge->dependency instanceof Module && ! $edge->dependency instanceof Feature) {
                continue;
            }

            $adjacency[$this->nodeKey($edge->dependent_type, $edge->dependent_id)][] = $edge->dependency;
        }

        $resolved = [];
        $queue = $roots;
        $visited = [];
        $offset = 0;

        while (isset($queue[$offset])) {
            $item = $queue[$offset++];
            $key = $this->nodeKey($item->getMorphClass(), $item->getKey());

            if (isset($visited[$key])) {
                continue;
            }

            $visited[$key] = true;
            $resolved[] = $item;

            foreach ($adjacency[$key] ?? [] as $dependency) {
                $queue[] = $dependency;
            }
        }

        return collect($resolved);
    }


    /**
     * Expand commercial roots into a deduplicated list of billable items.
     *
     * Dependency edges default to "included" for backwards compatibility.
     * Only dependencies explicitly marked "billable" become additional price
     * lines. Existing subscription coverage and bundle members are respected.
     *
     * @param array<int, array{item: Module|Feature|Bundle, quantity?: int}> $items
     * @param array<string, int> $alreadyCovered Catalog type/key => covered quantity
     * @return array<int, array{item: Module|Feature|Bundle, quantity: int, commercial_origin?: string, required_by?: array<int, string>}>
     */
    public function resolveBillableItems(array $items, array $alreadyCovered = []): array
    {
        $roots = [];
        $queue = [];
        $coveredByBundle = [];
        $rootKeys = [];

        foreach ($items as $input) {
            $item = $input['item'] ?? null;
            if (! $item instanceof Module && ! $item instanceof Feature && ! $item instanceof Bundle) {
                throw new DomainException('Each commercial selection must be a Module, Feature, or Bundle.');
            }

            $quantity = (int) ($input['quantity'] ?? 1);
            if ($quantity < 1 || $quantity > 1000) {
                throw new DomainException('Commercial selection quantity must be between 1 and 1000.');
            }

            $key = $this->catalogKey($item);
            if (isset($rootKeys[$key])) {
                throw new DomainException("Catalog item {$item->key} appears more than once in the commercial selection.");
            }

            $rootKeys[$key] = true;
            $roots[] = ['item' => $item, 'quantity' => $quantity, 'commercial_origin' => 'direct'];

            if ($item instanceof Bundle) {
                $item->loadMissing(['modules.features', 'features.module']);

                // Prevent two direct selections from charging for overlapping
                // bundle coverage. Compare the complete unique member set before
                // adding this bundle's own coverage to the shared map.
                $bundleMembers = [];
                foreach ($item->modules as $module) {
                    $bundleMembers[$this->catalogKey($module)] = true;
                    foreach ($module->features as $feature) {
                        if ($feature->status === 'active') {
                            $bundleMembers[$this->catalogKey($feature)] = true;
                        }
                    }
                }
                foreach ($item->features as $feature) {
                    $bundleMembers[$this->catalogKey($feature)] = true;
                }

                foreach (array_keys($bundleMembers) as $memberKey) {
                    if (isset($rootKeys[$memberKey]) || isset($coveredByBundle[$memberKey])) {
                        throw new DomainException("Bundle {$item->key} overlaps another selected Module, Feature, or Bundle.");
                    }
                }

                foreach ($item->modules as $module) {
                    $coveredByBundle[$this->catalogKey($module)] = max(
                        $coveredByBundle[$this->catalogKey($module)] ?? 0,
                        $quantity,
                    );
                    $queue[] = ['item' => $module, 'quantity' => $quantity, 'required_by' => $item->key];
                    foreach ($module->features as $feature) {
                        if ($feature->status === 'active') {
                            $coveredByBundle[$this->catalogKey($feature)] = max(
                                $coveredByBundle[$this->catalogKey($feature)] ?? 0,
                                $quantity,
                            );
                            $queue[] = ['item' => $feature, 'quantity' => $quantity, 'required_by' => $item->key];
                        }
                    }
                }
                foreach ($item->features as $feature) {
                    $coveredByBundle[$this->catalogKey($feature)] = max(
                        $coveredByBundle[$this->catalogKey($feature)] ?? 0,
                        $quantity,
                    );
                    $queue[] = ['item' => $feature, 'quantity' => $quantity, 'required_by' => $item->key];
                }
                continue;
            }

            $queue[] = ['item' => $item, 'quantity' => $quantity, 'required_by' => $item->key];

            if ($item instanceof Module) {
                $item->loadMissing('features');
                foreach ($item->features as $feature) {
                    if ($feature->status === 'active') {
                        $coveredByBundle[$this->catalogKey($feature)] = max(
                            $coveredByBundle[$this->catalogKey($feature)] ?? 0,
                            $quantity,
                        );
                        $queue[] = ['item' => $feature, 'quantity' => $quantity, 'required_by' => $item->key];
                    }
                }
            }
        }

        foreach ($roots as $root) {
            $item = $root['item'];
            if (! $item instanceof Bundle && isset($coveredByBundle[$this->catalogKey($item)])) {
                throw new DomainException("Catalog item {$item->key} is already covered by another selected Module or Bundle.");
            }
        }

        $edges = CatalogDependency::query()->with('dependency')->get();
        $adjacency = [];
        foreach ($edges as $edge) {
            if (! $edge->dependency instanceof Module && ! $edge->dependency instanceof Feature) {
                continue;
            }

            $adjacency[$this->nodeKey($edge->dependent_type, $edge->dependent_id)][] = [
                'item' => $edge->dependency,
                'policy' => strtolower((string) ($edge->metadata['billing_policy'] ?? 'included')),
            ];
        }

        $visited = [];
        $billable = [];
        $requiredBy = [];
        $quantities = [];
        $offset = 0;

        while (isset($queue[$offset])) {
            $current = $queue[$offset++];
            /** @var Module|Feature $dependent */
            $dependent = $current['item'];
            $dependentKey = $this->nodeKey($dependent->getMorphClass(), $dependent->getKey());
            $visitKey = $dependentKey.':'.$current['quantity'];

            if (isset($visited[$visitKey])) {
                continue;
            }
            $visited[$visitKey] = true;

            foreach ($adjacency[$dependentKey] ?? [] as $edge) {
                /** @var Module|Feature $dependency */
                $dependency = $edge['item'];
                $policy = $edge['policy'];

                if (! in_array($policy, ['included', 'billable', 'free'], true)) {
                    throw new DomainException("Catalog dependency for {$dependent->key} has an invalid billing policy.");
                }

                $this->assertDependencyAvailable($dependency);
                $dependencyKey = $this->catalogKey($dependency);
                $requiredBy[$dependencyKey] = array_values(array_unique([
                    ...($requiredBy[$dependencyKey] ?? []),
                    (string) $current['required_by'],
                ]));
                $quantities[$dependencyKey] = max($quantities[$dependencyKey] ?? 0, (int) $current['quantity']);

                if ($policy === 'billable'
                    && ! isset($rootKeys[$dependencyKey])
                    && ! isset($coveredByBundle[$dependencyKey])) {
                    $coveredQuantity = (int) ($alreadyCovered[$dependencyKey] ?? 0);
                    $requiredQuantity = (int) $quantities[$dependencyKey];

                    if ($coveredQuantity > 0 && $coveredQuantity < $requiredQuantity) {
                        throw new DomainException("Required dependency {$dependency->key} is already subscribed at a lower quantity; quantity upgrades must be purchased explicitly.");
                    }

                    if ($coveredQuantity === 0) {
                        if ($dependency instanceof Feature && ! $dependency->is_individually_purchasable) {
                            throw new DomainException("Required dependency {$dependency->key} is billable but not individually purchasable.");
                        }
                        if ($dependency instanceof Module && $dependency->is_core) {
                            throw new DomainException('Core modules cannot be charged as catalog dependencies.');
                        }

                        $billable[$dependencyKey] = [
                            'item' => $dependency,
                            'quantity' => $requiredQuantity,
                            'commercial_origin' => 'required_dependency',
                        ];
                    }
                }

                $queue[] = [
                    'item' => $dependency,
                    'quantity' => (int) $current['quantity'],
                    'required_by' => (string) $current['required_by'],
                ];

                if ($dependency instanceof Module) {
                    $dependency->loadMissing('features');
                    foreach ($dependency->features as $feature) {
                        if ($feature->status === 'active') {
                            $coveredByBundle[$this->catalogKey($feature)] = max(
                                $coveredByBundle[$this->catalogKey($feature)] ?? 0,
                                (int) $current['quantity'],
                            );
                            $queue[] = [
                                'item' => $feature,
                                'quantity' => (int) $current['quantity'],
                                'required_by' => (string) $current['required_by'],
                            ];
                        }
                    }
                }
            }
        }

        foreach ($billable as $key => &$line) {
            $line['required_by'] = $requiredBy[$key] ?? [];
        }
        unset($line);

        return [...$roots, ...array_values($billable)];
    }

    private function assertDependencyAvailable(Module|Feature $dependency): void
    {
        if ($dependency->status !== 'active') {
            throw new DomainException("Required dependency {$dependency->key} is not active.");
        }

        if ($dependency instanceof Feature) {
            $dependency->loadMissing('module');
            if ($dependency->module === null || $dependency->module->status !== 'active') {
                throw new DomainException("Required dependency {$dependency->key} has an unavailable parent Module.");
            }
        }
    }

    private function catalogKey(Module|Feature|Bundle $item): string
    {
        return $item->getMorphClass().':'.$item->key;
    }

    private function nodeKey(string $type, string|int $id): string
    {
        return "{$type}:{$id}";
    }
}
