<?php

namespace RMS\Shop\Services;

use RMS\Shop\Support\MediaLibrarySchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RMS\Shop\Models\VideoLibrary;
use RMS\Shop\Models\VideoLibraryFolder;
use RMS\Shop\Services\VideoLibraryService;

class VideoLibraryFolderService
{
    public function getTreeForPlugin(?int $selectedId = null, bool $includeCounts = true): array
    {
        if (! MediaLibrarySchema::hasVideoFolders()) {
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

        $folders = VideoLibraryFolder::query()
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        $canCountByFolder = $includeCounts && MediaLibrarySchema::hasVideoFolderColumn();
        $unfiledCount = $canCountByFolder
            ? VideoLibrary::query()->whereNull('folder_id')->count()
            : 0;

        $tree = [[
            'key' => 'unfiled',
            'title' => 'بدون پوشه'.($canCountByFolder ? ' ('.$unfiledCount.')' : ''),
            'folder' => true,
            'expanded' => true,
            'data' => [
                'id' => null,
                'type' => 'virtual',
                'path' => '',
            ],
        ]];

        $tree = array_merge($tree, $this->buildBranch($folders, null, $includeCounts));

        if ($selectedId) {
            $this->markSelected($tree, (string) $selectedId);
        }

        return $tree;
    }

    public function breadcrumbs(?VideoLibraryFolder $folder): array
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

    public function createFolder(?int $parentId, string $name): VideoLibraryFolder
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('نام پوشه الزامی است.');
        }

        $parent = $parentId ? VideoLibraryFolder::query()->findOrFail($parentId) : null;
        $slug = $this->makeUniqueSlug($name, $parentId);
        $path = $parent ? $parent->path.'/'.$slug : $slug;

        return VideoLibraryFolder::query()->create([
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => $slug,
            'path' => $path,
            'sort' => $this->nextSort($parentId),
        ]);
    }

    public function updateFolder(VideoLibraryFolder $folder, string $name): VideoLibraryFolder
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('نام پوشه الزامی است.');
        }

        return DB::transaction(function () use ($folder, $name) {
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

    public function deleteFolder(VideoLibraryFolder $folder): void
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
            ->map(function (VideoLibraryFolder $folder) use ($folders, $includeCounts) {
                $count = $includeCounts ? $folder->videos()->count() : 0;

                return [
                    'key' => (string) $folder->id,
                    'title' => $folder->name.($includeCounts ? ' ('.$count.')' : ''),
                    'folder' => true,
                    'expanded' => true,
                    'children' => $this->buildBranch($folders, $folder->id, $includeCounts),
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
        $base = Str::slug($name) ?: 'folder';
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
        return VideoLibraryFolder::query()
            ->where('parent_id', $parentId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    protected function nextSort(?int $parentId): int
    {
        return ((int) VideoLibraryFolder::query()->where('parent_id', $parentId)->max('sort')) + 10;
    }

    protected function rebuildDescendantPaths(VideoLibraryFolder $folder): void
    {
        foreach ($folder->children as $child) {
            $child->update(['path' => $folder->path.'/'.$child->slug]);
            $this->rebuildDescendantPaths($child->fresh());
        }
    }

    protected function relocateFolderTreeFiles(VideoLibraryFolder $rootFolder): void
    {
        $folderIds = VideoLibraryFolder::query()
            ->where('path', $rootFolder->path)
            ->orWhere('path', 'like', $rootFolder->path.'/%')
            ->pluck('id');

        VideoLibrary::query()
            ->whereIn('folder_id', $folderIds)
            ->with('folder')
            ->get()
            ->each(fn (VideoLibrary $video) => app(VideoLibraryService::class)->syncVideoStoragePath($video));
    }
}
