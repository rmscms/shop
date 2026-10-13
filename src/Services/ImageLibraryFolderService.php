<?php

namespace RMS\Shop\Services;

use RMS\Shop\Support\MediaLibrarySchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RMS\Shop\Models\ImageLibrary;
use RMS\Shop\Models\ImageLibraryFolder;

class ImageLibraryFolderService
{
    public function getTreeForPlugin(?int $selectedId = null, bool $includeCounts = true): array
    {
        if (! MediaLibrarySchema::hasImageFolders()) {
            return [[
                'key' => 'unfiled',
                'title' => 'بدون پوشه',
                'folder' => true,
                'expanded' => true,
                'selected' => true,
                'data' => [
                    'id' => null,
                    'type' => 'virtual',
                    'path' => '',
                ],
            ]];
        }

        $folders = ImageLibraryFolder::query()
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        $unfiledCount = $includeCounts
            ? ImageLibrary::query()->whereNull('folder_id')->count()
            : 0;

        $tree = [[
            'key' => 'unfiled',
            'title' => 'بدون پوشه'.($includeCounts ? ' ('.$unfiledCount.')' : ''),
            'folder' => true,
            'expanded' => true,
            'data' => [
                'id' => null,
                'type' => 'virtual',
                'path' => '',
            ],
        ]];

        $children = $this->buildBranch($folders, null, $includeCounts);
        $tree = array_merge($tree, $children);

        if ($selectedId) {
            $this->markSelected($tree, (string) $selectedId);
        }

        return $tree;
    }

    public function breadcrumbs(?ImageLibraryFolder $folder): array
    {
        if (! $folder) {
            return [[
                'id' => null,
                'name' => 'بدون پوشه',
                'path' => '',
            ]];
        }

        $crumbs = [];
        $current = $folder;

        while ($current) {
            array_unshift($crumbs, [
                'id' => $current->id,
                'name' => $current->name,
                'path' => $current->path,
            ]);
            $current = $current->parent;
        }

        return $crumbs;
    }

    public function createFolder(?int $parentId, string $name): ImageLibraryFolder
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('نام پوشه الزامی است.');
        }

        $parent = $parentId ? ImageLibraryFolder::query()->findOrFail($parentId) : null;
        $slug = $this->makeUniqueSlug($name, $parentId);
        $path = $parent ? $parent->path.'/'.$slug : $slug;

        return ImageLibraryFolder::query()->create([
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => $slug,
            'path' => $path,
            'sort' => $this->nextSort($parentId),
        ]);
    }

    public function updateFolder(ImageLibraryFolder $folder, string $name): ImageLibraryFolder
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('نام پوشه الزامی است.');
        }

        return DB::transaction(function () use ($folder, $name) {
            $oldPath = $folder->path;
            $slug = $this->makeUniqueSlug($name, $folder->parent_id, $folder->id);
            $parentPath = $folder->parent?->path;
            $newPath = $parentPath ? $parentPath.'/'.$slug : $slug;

            $folder->update([
                'name' => $name,
                'slug' => $slug,
                'path' => $newPath,
            ]);

            $folder = $folder->fresh(['parent']);
            $this->rebuildDescendantPaths($folder);
            $this->relocateFolderTreeFiles($folder);

            return $folder->fresh();
        });
    }

    public function deleteFolder(ImageLibraryFolder $folder): void
    {
        if (! $folder->isEmpty()) {
            throw new \RuntimeException('پوشه باید خالی باشد تا حذف شود.');
        }

        $folder->delete();
    }

    protected function buildBranch(Collection $folders, ?int $parentId, bool $includeCounts): array
    {
        return $folders
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (ImageLibraryFolder $folder) use ($folders, $includeCounts) {
                $count = $includeCounts ? $folder->images()->count() : 0;
                $children = $this->buildBranch($folders, $folder->id, $includeCounts);

                return [
                    'key' => (string) $folder->id,
                    'title' => $folder->name.($includeCounts ? ' ('.$count.')' : ''),
                    'folder' => true,
                    'expanded' => true,
                    'children' => $children,
                    'data' => [
                        'id' => $folder->id,
                        'type' => 'folder',
                        'path' => $folder->path,
                        'parent_id' => $folder->parent_id,
                    ],
                ];
            })
            ->all();
    }

    protected function markSelected(array &$nodes, string $selectedId): void
    {
        foreach ($nodes as &$node) {
            if (($node['key'] ?? '') === $selectedId) {
                $node['selected'] = true;
            }

            if (! empty($node['children'])) {
                $this->markSelected($node['children'], $selectedId);
            }
        }
    }

    protected function makeUniqueSlug(string $name, ?int $parentId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'folder';
        }

        $slug = $base;
        $counter = 2;

        while ($this->slugExists($slug, $parentId, $ignoreId)) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function slugExists(string $slug, ?int $parentId, ?int $ignoreId = null): bool
    {
        return ImageLibraryFolder::query()
            ->where('parent_id', $parentId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    protected function nextSort(?int $parentId): int
    {
        $max = ImageLibraryFolder::query()
            ->where('parent_id', $parentId)
            ->max('sort');

        return ((int) $max) + 10;
    }

    protected function rebuildDescendantPaths(ImageLibraryFolder $folder): void
    {
        foreach ($folder->children as $child) {
            $newPath = $folder->path.'/'.$child->slug;
            $child->update(['path' => $newPath]);
            $this->rebuildDescendantPaths($child->fresh());
        }
    }

    protected function relocateFolderTreeFiles(ImageLibraryFolder $rootFolder): void
    {
        $folderIds = $this->collectDescendantIds($rootFolder);

        ImageLibrary::query()
            ->whereIn('folder_id', $folderIds)
            ->with('folder')
            ->get()
            ->each(function (ImageLibrary $image) {
                \RMS\Shop\Services\ImageLibraryService::syncImageStoragePath($image);
            });
    }

    protected function collectDescendantIds(ImageLibraryFolder $folder): array
    {
        return ImageLibraryFolder::query()
            ->where('path', $folder->path)
            ->orWhere('path', 'like', $folder->path.'/%')
            ->pluck('id')
            ->all();
    }
}
