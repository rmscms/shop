<?php

namespace RMS\Shop\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RMS\Shop\Jobs\TranscodeVideo;
use RMS\Shop\Models\VideoAssignment;
use RMS\Shop\Models\VideoLibrary;
use Illuminate\Database\Eloquent\Model;

class VideoLibraryService
{
    public function upload(UploadedFile $file, ?string $title = null): VideoLibrary
    {
        // Generate safe filename (UUID + extension)
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid() . '.' . $extension;
        $path = $file->storeAs('uploads/products/videos/orig', $filename, 'public');

        $video = VideoLibrary::create([
            'filename' => $filename,
            'title' => $title,
            'path' => $path,
            'size_bytes' => $file->getSize(),
        ]);

        TranscodeVideo::dispatch($video->id);

        return $video;
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

    public function searchVideos(string $search = '', int $page = 1, int $perPage = 20): array
    {
        $query = VideoLibrary::query()
            ->withCount(['assignments as products_count' => function($q) {
                $q->where('assignable_type', 'RMS\Shop\Models\Product');
            }]);

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage, ['*'], 'page', $page)->toArray();
    }

    public function searchVideosForProduct(Model $product, string $search = '', int $page = 1, int $perPage = 20): array
    {
        // گرفتن IDهای ویدیوهایی که به این محصول اساین شدن
        $assignedVideoIds = $product->assignedVideos()->pluck('video_id')->toArray();

        $query = VideoLibrary::query();

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
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
}
