<?php

namespace RMS\Shop\Helpers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RMS\Shop\Models\AvifDirectory;

class AvifHelper
{
    protected static bool $directoriesSeeded = false;

    protected static function defaultDirectories(): array
    {
        return [
            ['type' => 'public', 'dir' => 'uploads/products', 'is_default' => true],
            ['type' => 'public', 'dir' => 'uploads/products/combinations', 'is_default' => true],
            ['type' => 'public', 'dir' => 'uploads/products/library', 'is_default' => true],
            ['type' => 'storage', 'dir' => 'uploads/products/library/orig', 'is_default' => true],
            ['type' => 'public', 'dir' => 'uploads/image-library', 'is_default' => true],
            ['type' => 'public', 'dir' => 'vendor/shop/admin/images', 'is_default' => true],
        ];
    }

    protected static function ensureDirectoriesSeeded(): void
    {
        if (self::$directoriesSeeded) {
            return;
        }

        $now = now();
        foreach (self::defaultDirectories() as $dir) {
            $path = trim($dir['dir'], '/');
            $type = $dir['type'] ?? 'public';

            AvifDirectory::query()->firstOrCreate(
                ['path' => $path, 'type' => $type],
                [
                    'active' => true,
                    'is_default' => $dir['is_default'] ?? false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        self::$directoriesSeeded = true;
    }

    protected static function directoryCollection(): Collection
    {
        self::ensureDirectoriesSeeded();

        return AvifDirectory::query()
            ->orderBy('path')
            ->get()
            ->map(function (AvifDirectory $dir) {
                $dir->path = trim($dir->path, '/');
                return $dir;
            });
    }

    /**
     * Determine configured directories for AVIF operations.
     *
     * @return array<int,array{type:string,dir:string}>
     */
    public static function getTargetDirectories(): array
    {
        $directories = self::directoryCollection()
            ->where('active', true)
            ->values();

        if ($directories->isEmpty()) {
            return self::defaultDirectories();
        }

        return $directories->map(function (AvifDirectory $dir) {
            return [
                'type' => $dir->type ?? 'public',
                'dir' => trim($dir->path, '/'),
            ];
        })->all();
    }

    public static function getDefaultImage(): string
    {
        return asset('admin/images/no-avif-placeholder.svg');
    }

    public static function getAvifPath(string $originalPath): string
    {
        if (empty($originalPath)) {
            return self::getDefaultImage();
        }

        $fullPath = str_starts_with($originalPath, '/')
            ? public_path($originalPath)
            : public_path('/' . $originalPath);

        $pathInfo = pathinfo($fullPath);
        $avifPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.avif';
        $relative = ltrim(str_replace(public_path(), '', $avifPath), '/');

        if (File::exists($avifPath)) {
            return asset($relative);
        }

        return self::getDefaultImage();
    }

    public static function convertToAvif(string $imagePath, int $quality = 80): bool
    {
        try {
            if (!File::exists($imagePath)) {
                return false;
            }

            $pathInfo = pathinfo($imagePath);
            $avifPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.avif';

            if (class_exists(\Intervention\Image\ImageManager::class)) {
                try {
                    $manager = null;
                    try {
                        $manager = \Intervention\Image\ImageManager::imagick();
                    } catch (\Throwable $e) {
                        $manager = \Intervention\Image\ImageManager::gd();
                    }

                    $img = $manager->read($imagePath);
                    $img->save($avifPath, $quality);
                    return true;
                } catch (\Throwable $e) {
                    Log::warning('AVIF convert via Intervention failed', [
                        'path' => $imagePath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (function_exists('imageavif')) {
                $resource = match (strtolower($pathInfo['extension'] ?? '')) {
                    'jpg', 'jpeg' => @imagecreatefromjpeg($imagePath),
                    'png' => @imagecreatefrompng($imagePath),
                    'gif' => @imagecreatefromgif($imagePath),
                    'webp' => @imagecreatefromwebp($imagePath),
                    default => null,
                };

                if ($resource) {
                    $result = @imageavif($resource, $avifPath, $quality);
                    if (is_resource($resource) || (\PHP_VERSION_ID >= 80000 && $resource instanceof \GdImage)) {
                        @imagedestroy($resource);
                    }
                    return (bool) $result;
                }
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('AVIF conversion error', [
                'path' => $imagePath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Clean AVIF files for one directory or all configured directories.
     *
     * @return array{total_deleted:int,details:array<string,int>}
     */
    public static function cleanAvifFiles(?string $directory = null): array
    {
        $targets = $directory
            ? self::directoryCollection()->where('path', trim($directory, '/'))->all()
            : self::directoryCollection()->where('active', true)->all();

        if ($directory && empty($targets)) {
            $targets = [
                (object) [
                    'path' => trim($directory, '/'),
                    'type' => 'public',
                    'active' => true,
                ],
            ];
        }

        $details = [];
        $total = 0;

        foreach ($targets as $target) {
            $dir = $target->path;
            $deleted = ($target->type ?? 'public') === 'storage'
                ? self::cleanStorageDirectory($dir)
                : self::cleanPublicDirectory($dir);

            $details[$dir] = $deleted;
            $total += $deleted;
        }

        return [
            'total_deleted' => $total,
            'details' => $details,
        ];
    }

    /**
     * Aggregate stats for all directories or a single directory.
     */
    public static function getDirectoryStats(?string $directory = null): array
    {
        if ($directory !== null) {
            $record = self::directoryCollection()->firstWhere('path', trim($directory, '/'));
            $type = $record?->type ?? 'public';

            return self::buildDirectoryStats(trim($directory, '/'), $type);
        }

        $summary = [
            'total_images' => 0,
            'total_avif' => 0,
            'missing_avif' => 0,
            'conversion_rate' => 0,
            'directories' => [],
        ];

        foreach (self::directoryCollection() as $dir) {
            $stats = self::buildDirectoryStats($dir->path, $dir->type);
            $stats['id'] = $dir->id;
            $stats['type'] = $dir->type;
            $stats['active'] = $dir->active;
            $stats['is_default'] = $dir->is_default;

            $summary['directories'][$dir->path] = $stats;

            if (!$dir->active) {
                continue;
            }

            $summary['total_images'] += $stats['total_images'];
            $summary['total_avif'] += $stats['avif_files'];
            $summary['missing_avif'] += $stats['missing_avif'];
        }

        $summary['conversion_rate'] = $summary['total_images'] > 0
            ? round(($summary['total_avif'] / $summary['total_images']) * 100, 2)
            : 0;

        return $summary;
    }

    /**
     * Clean AVIF files for a single public directory.
     */
    protected static function cleanPublicDirectory(string $relativeDir): int
    {
        $absolute = public_path($relativeDir);
        $deleted = 0;

        if (!File::isDirectory($absolute)) {
            return 0;
        }

        foreach (File::glob($absolute . DIRECTORY_SEPARATOR . '*.avif') as $file) {
            if (File::delete($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    protected static function cleanStorageDirectory(string $relativeDir): int
    {
        $deleted = 0;
        $disk = Storage::disk('public');

        if (!$disk->exists($relativeDir)) {
            return 0;
        }

        foreach ($disk->allFiles($relativeDir) as $file) {
            if (Str::endsWith(strtolower($file), '.avif') && $disk->delete($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Build statistics for a directory (public or storage).
     */
    protected static function buildDirectoryStats(string $relativeDir, string $type = 'public'): array
    {
        $stats = [
            'path' => $relativeDir,
            'type' => $type,
            'exists' => false,
            'total_images' => 0,
            'avif_files' => 0,
            'missing_avif' => 0,
            'conversion_rate' => 0,
        ];

        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if ($type === 'storage') {
            $disk = Storage::disk('public');
            if (!$disk->exists($relativeDir)) {
                return $stats;
            }

            $stats['exists'] = true;
            $files = $disk->allFiles($relativeDir);

            foreach ($files as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, $extensions, true)) {
                    continue;
                }

                if (!Str::contains($file, '/orig/')) {
                    continue;
                }

                if (!$disk->exists($file)) {
                    continue;
                }

                $stats['total_images']++;
                $base = pathinfo($file, PATHINFO_FILENAME);
                $avifRel = Str::beforeLast($file, '/orig/') . '/avif/' . $base . '.avif';

                if ($disk->exists($avifRel)) {
                    $stats['avif_files']++;
                }
            }
        } else {
            $absolute = public_path(trim($relativeDir, '/'));
            if (!File::isDirectory($absolute)) {
                return $stats;
            }

            $stats['exists'] = true;

            foreach (File::allFiles($absolute) as $file) {
                if (!in_array(strtolower($file->getExtension()), $extensions, true)) {
                    continue;
                }

                $stats['total_images']++;
                $avifPath = $file->getPath() . DIRECTORY_SEPARATOR . pathinfo($file->getFilename(), PATHINFO_FILENAME) . '.avif';

                if (File::exists($avifPath)) {
                    $stats['avif_files']++;
                }
            }
        }

        $stats['missing_avif'] = max(0, $stats['total_images'] - $stats['avif_files']);
        $stats['conversion_rate'] = $stats['total_images'] > 0
            ? round(($stats['avif_files'] / $stats['total_images']) * 100, 2)
            : 0;

        return $stats;
    }
}
