<?php

namespace RMS\Shop\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RMS\Shop\Jobs\TranscodeVideo;
use RMS\Shop\Models\VideoAssignment;
use RMS\Shop\Models\VideoLibrary;
use RMS\Shop\Models\VideoLibraryFolder;
use RMS\Shop\Support\MediaLibrarySchema;
use Illuminate\Database\Eloquent\Model;

class VideoLibraryService
{
    public function upload(UploadedFile $file, ?string $title = null, ?VideoLibraryFolder $folder = null): VideoLibrary
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid() . '.' . $extension;
        $origDir = static::origDirectoryForFolder($folder);
        Storage::disk('public')->makeDirectory($origDir);
        $path = $file->storeAs($origDir, $filename, 'public');

        $payload = [
            'filename' => $filename,
            'path' => $path,
            'size_bytes' => $file->getSize(),
        ];

        if (MediaLibrarySchema::hasVideoTitle()) {
            $payload['title'] = $title;
        }

        if (MediaLibrarySchema::hasVideoFolderColumn()) {
            $payload['folder_id'] = $folder?->id;
        }

        $video = VideoLibrary::create($payload);

        TranscodeVideo::dispatch($video->id);

        return $video;
    }

    public function moveVideo(VideoLibrary $video, ?VideoLibraryFolder $targetFolder): VideoLibrary
    {
        return DB::transaction(function () use ($video, $targetFolder) {
            if (MediaLibrarySchema::hasVideoFolderColumn()) {
                $video->update(['folder_id' => $targetFolder?->id]);
            }

            return $this->syncVideoStoragePath($video->fresh(
                MediaLibrarySchema::hasVideoFolderColumn() ? ['folder'] : []
            ));
        });
    }

    public function syncVideoStoragePath(VideoLibrary $video): VideoLibrary
    {
        $folder = MediaLibrarySchema::hasVideoFolderColumn() ? $video->folder : null;
        $origDir = static::origDirectoryForFolder($folder);
        $newPath = $origDir.'/'.$video->filename;

        if ($video->path === $newPath) {
            return $video;
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory($origDir);

        if ($disk->exists($video->path)) {
            $disk->move($video->path, $newPath);
        }

        $video->update(['path' => $newPath]);

        return $video->fresh(['folder']);
    }

    public static function origDirectoryForFolder(?VideoLibraryFolder $folder): string
    {
        $base = 'uploads/products/videos/orig';
        $subPath = trim((string) ($folder?->path ?? ''), '/');

        return $subPath === '' ? $base : $base.'/'.$subPath;
    }

    public function delete(VideoLibrary $video): void
    {
        Storage::disk('public')->delete($video->path);
        Storage::disk('public')->delete($video->hls_path);
        Storage::disk('public')->delete($video->poster_path);
        $video->delete();
    }

    public function assignVideosTo(Model $assignable, array $videoIds): void
    {
        foreach ($videoIds as $videoId) {
            VideoAssignment::firstOrCreate(
                [
                    'video_id' => $videoId,
                    'assignable_id' => $assignable->id,
                    'assignable_type' => get_class($assignable),
                ],
                [
                    'is_main' => false,
                    'sort' => 0,
                ]
            );
        }
    }

    public function detachVideosFrom(Model $assignable, array $videoIds): void
    {
        VideoAssignment::where('assignable_id', $assignable->id)
            ->where('assignable_type', get_class($assignable))
            ->whereIn('video_id', $videoIds)
            ->delete();
    }

    public function setMainVideo(VideoLibrary $video, Model $assignable): void
    {
        // Reset all videos to not main
        VideoAssignment::where('assignable_id', $assignable->id)
            ->where('assignable_type', get_class($assignable))
            ->update(['is_main' => false]);
        
        // Set specified video as main
        VideoAssignment::where('assignable_id', $assignable->id)
            ->where('assignable_type', get_class($assignable))
            ->where('video_id', $video->id)
            ->update(['is_main' => true]);
    }

    public function updateSort(Model $assignable, array $sortOrder): void
    {
        foreach ($sortOrder as $order => $videoId) {
            VideoAssignment::where('assignable_id', $assignable->id)
                ->where('assignable_type', get_class($assignable))
                ->where('video_id', $videoId)
                ->update(['sort' => $order]);
        }
    }

    public function searchVideos(string $search = '', int $page = 1, int $perPage = 20, ?int $folderId = null, bool $unfiledOnly = false): array
    {
        $query = VideoLibrary::query()
            ->withCount(['assignments as products_count' => function($q) {
                $q->where('assignable_type', 'RMS\Shop\Models\Product');
            }]);

        $this->applyVideoFolderFilter($query, $folderId, $unfiledOnly);

        if ($search) {
            $query->where(function ($builder) use ($search) {
                $builder->where('filename', 'like', "%{$search}%");
                if (MediaLibrarySchema::hasVideoTitle()) {
                    $builder->orWhere('title', 'like', "%{$search}%");
                }
            });
        }

        return $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->toArray();
    }

    public function searchVideosForProduct(Model $product, string $search = '', int $page = 1, int $perPage = 20): array
    {
        // گرفتن IDهای ویدیوهایی که به این محصول اساین شدن
        $assignedVideoIds = $product->assignedVideos()->pluck('video_id')->toArray();

        $query = VideoLibrary::query();

        if ($search) {
            if (MediaLibrarySchema::hasVideoTitle()) {
                $query->where('title', 'like', "%{$search}%");
            } else {
                $query->where('filename', 'like', "%{$search}%");
            }
        }

        // اولویت: ویدیوهای اساین نشده اول، بعد اساین شده‌ها
        $query->orderByRaw('CASE WHEN id IN (' . implode(',', $assignedVideoIds ?: [0]) . ') THEN 1 ELSE 0 END')
              ->orderBy('created_at', 'desc');

        return $query->paginate($perPage, ['*'], 'page', $page)->toArray();
    }

    public function getProductVideos(Model $product)
    {
        return $product->assignedVideos()->orderBy('sort', 'asc')->orderBy('is_main', 'desc')->get();
    }
    
    public function getCombinationVideos(Model $combination)
    {
        return $combination->assignedVideos()->orderBy('sort', 'asc')->orderBy('is_main', 'desc')->get();
    }

    protected function applyVideoFolderFilter($query, ?int $folderId, bool $unfiledOnly): void
    {
        if (! MediaLibrarySchema::hasVideoFolderColumn()) {
            return;
        }

        if ($unfiledOnly) {
            $query->whereNull('folder_id');
        } elseif ($folderId) {
            $query->where('folder_id', $folderId);
        }
    }
}
