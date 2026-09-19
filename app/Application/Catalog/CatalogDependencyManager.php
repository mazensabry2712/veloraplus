<?php

namespace App\Application\Catalog;

use App\Models\CatalogDependency;
use App\Models\Feature;
use App\Models\Module;
use DomainException;

final class CatalogDependencyManager
{
    public function add(Module|Feature $dependent, Module|Feature $dependency): CatalogDependency
    {
        if ($dependent->getKey() === $dependency->getKey()
            && $dependent->getMorphClass() === $dependency->getMorphClass()) {
            throw new DomainException('A catalog item cannot depend on itself.');
        }

        if (CatalogDependency::query()
            ->where('dependent_type', $dependent->getMorphClass())
            ->where('dependent_id', $dependent->getKey())
            ->where('dependency_type', $dependency->getMorphClass())
            ->where('dependency_id', $dependency->getKey())
            ->exists()) {
            throw new DomainException('The catalog dependency already exists.');
        }

        if ($this->canReach($dependency, $dependent)) {
            throw new DomainException('The catalog dependency would create a circular dependency.');
        }

        return CatalogDependency::query()->create([
            'dependent_type' => $dependent->getMorphClass(),
            'dependent_id' => $dependent->getKey(),
            'dependency_type' => $dependency->getMorphClass(),
            'dependency_id' => $dependency->getKey(),
        ]);
    }

    private function canReach(Module|Feature $from, Module|Feature $wanted): bool
    {
        $adjacency = CatalogDependency::query()
            ->get(['dependent_type', 'dependent_id', 'dependency_type', 'dependency_id'])
            ->groupBy(fn (CatalogDependency $dependency): string => $this->nodeKey(
                $dependency->dependent_type,
                $dependency->dependent_id
            ));

        $queue = [$this->nodeKey($from->getMorphClass(), $from->getKey())];
        $visited = [];
        $wantedKey = $this->nodeKey($wanted->getMorphClass(), $wanted->getKey());
        $offset = 0;

        while (isset($queue[$offset])) {
            $current = $queue[$offset++];

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            if ($current === $wantedKey) {
                return true;
            }

            foreach ($adjacency->get($current, collect()) as $edge) {
                $queue[] = $this->nodeKey($edge->dependency_type, $edge->dependency_id);
            }
        }

        return false;
    }

    private function nodeKey(string $type, string|int $id): string
    {
        return "{$type}:{$id}";
    }
}
