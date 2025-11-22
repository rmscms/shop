<?php

namespace RMS\Shop\Services;

use RMS\Shop\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryTreeService
{
    protected const CACHE_ACTIVE = 'shop:categories:tree:active';
    protected const CACHE_ALL = 'shop:categories:tree:all';

    public function defaultCategoryId(): ?int
    {
        return $this->ensureDefaultCategory()?->id;
    }

    public function getTree(bool $activeOnly = true): array
    {
        $cacheKey = $activeOnly ? self::CACHE_ACTIVE : self::CACHE_ALL;
        $ttl = (int) config('shop.categories.cache_ttl', 300);
        if ($ttl <= 0) {
            return $this->buildTree($activeOnly);
        }

        return Cache::remember($cacheKey, $ttl, function () use ($activeOnly) {
            return $this->buildTree($activeOnly);
        });
    }

    public function getTreeForPlugin(?int $selectedId = null, bool $activeOnly = true): array
    {
        $categories = $this->fetchCategories($activeOnly);
        $map = $categories->keyBy(fn (Category $cat): int => (int) $cat->id)->all();
        $ancestors = $this->collectAncestorIds($map, $selectedId);

        return $this->buildTreeNodes($categories, $selectedId, $ancestors);
    }

    public function flatOptions(bool $activeOnly = false): array
    {
        $categories = $this->fetchCategories($activeOnly);
        $grouped = $categories->groupBy(fn (Category $cat) => $cat->parent_id ?? 0);

        return $this->buildFlatOptions($grouped, null);
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_ACTIVE);
        Cache::forget(self::CACHE_ALL);
    }

    protected function buildTree(bool $activeOnly): array
    {
        $categories = $this->fetchCategories($activeOnly);

        return $this->buildTreeNodes($categories, null, []);
    }

    protected function fetchCategories(bool $activeOnly): Collection
    {
        $this->ensureDefaultCategory();

        $query = Category::query()->ordered();
        if ($activeOnly) {
            $query->where('active', true);
        }

        return $query->get(['id', 'name', 'slug', 'parent_id', 'active', 'sort']);
    }

    protected function ensureDefaultCategory(): ?Category
    {
        return Category::ensureDefault();
    }

    protected function buildTreeNodes(Collection $categories, ?int $selectedId, array $expandedIds): array
    {
        $grouped = $categories->groupBy(fn (Category $cat) => $cat->parent_id ?? 0);

        return $this->buildNodeChildren($grouped, $selectedId, $expandedIds, null);
    }

    protected function buildNodeChildren(Collection $grouped, ?int $selectedId, array $expandedIds, ?int $parentId, int $depth = 0): array
    {
        $bucket = $grouped->get($parentId ?? 0, collect());

        return $bucket->map(function (Category $cat) use ($grouped, $selectedId, $expandedIds, $depth) {
            $children = $this->buildNodeChildren($grouped, $selectedId, $expandedIds, (int) $cat->id, $depth + 1);

            $node = [
                'title' => (string) $cat->name,
                'key' => (string) $cat->id,
                'folder' => count($children) > 0,
                'data' => [
                    'id' => (int) $cat->id,
                    'slug' => (string) $cat->slug,
                    'active' => (bool) $cat->active,
                    'sort' => (int) $cat->sort,
                    'depth' => $depth,
                ],
            ];

            if ($selectedId !== null && (int) $cat->id === (int) $selectedId) {
                $node['selected'] = true;
                $node['active'] = true;
            }

            if (in_array((int) $cat->id, $expandedIds, true) || $depth === 0) {
                $node['expanded'] = true;
            }

            if ($children) {
                $node['children'] = $children;
            }

            if (!(bool) $cat->active) {
                $node['extraClasses'] = 'text-muted';
            }

            return $node;
        })->values()->all();
    }

    protected function collectAncestorIds(array $map, ?int $selectedId): array
    {
        if (!$selectedId || !isset($map[$selectedId])) {
            return [];
        }

        $ancestors = [];
        $current = $selectedId;
        $guard = 0;

        while ($current && isset($map[$current]) && $guard < 1000) {
            /** @var Category $node */
            $node = $map[$current];
            $parentId = $node->parent_id ? (int) $node->parent_id : null;
            if ($parentId === null) {
                break;
            }
            $ancestors[] = $parentId;
            $current = $parentId;
            $guard++;
        }

        return array_values(array_unique($ancestors));
    }

    protected function buildFlatOptions(Collection $grouped, ?int $parentId, int $depth = 0): array
    {
        $bucket = $grouped->get($parentId ?? 0, collect());
        $options = [];

        foreach ($bucket as $category) {
            $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
            $options[(int) $category->id] = $prefix . $category->name;
            $childOptions = $this->buildFlatOptions($grouped, (int) $category->id, $depth + 1);
            if (!empty($childOptions)) {
                $options += $childOptions;
            }
        }

        return $options;
    }
}

