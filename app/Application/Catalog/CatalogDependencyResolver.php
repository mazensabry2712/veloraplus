<?php

namespace App\Application\Catalog;

use App\Models\CatalogDependency;
use App\Models\Feature;
use App\Models\Module;
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

    private function nodeKey(string $type, string|int $id): string
    {
        return "{$type}:{$id}";
    }
}
