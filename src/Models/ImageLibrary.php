<?php
// git-trigger
namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use RMS\Shop\Models\Category;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductCombination;
use RMS\Shop\Models\ImageAssignment;

class ImageLibrary extends Model
{
    protected $table = 'image_library';

    public $timestamps = true;

    protected $fillable = [
        'filename',
        'original_name',
        'path',
        'size_bytes',
        'mime_type',
        'folder_id',
        'alt',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'folder_id' => 'integer',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(ImageLibraryFolder::class, 'folder_id');
    }

    /**
     * Get all assignments for this image (products, combinations)
     */
    public function assignments()
    {
        return $this->hasMany(ImageAssignment::class, 'image_id');
    }

    /** Categories that use this image as menu photo. */
    public function menuCategories(): HasMany
    {
        return $this->hasMany(Category::class, 'menu_image_id');
    }

    /**
     * Get all size variants for this image
     */
    public function variants()
    {
        return $this->hasMany(\RMS\Shop\Models\ImageSizeVariant::class, 'image_id');
    }

    /**
     * Get products this image is assigned to
     */
    public function products()
    {
        // Pivot column is image_id (not image_library_id from getForeignKey()).
        return $this->morphedByMany(Product::class, 'assignable', 'image_assignments', 'image_id')
            ->withPivot(['is_main', 'sort']);
    }

    /**
     * Get product combinations this image is assigned to
     */
    public function productCombinations()
    {
        return $this->morphedByMany(ProductCombination::class, 'assignable', 'image_assignments', 'image_id')
            ->withPivot(['is_main', 'sort']);
    }

    /**
     * Get full URL for the image
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * Get AVIF URL if exists
     */
    public function getAvifUrlAttribute(): ?string
    {
        $avifPath = static::resolveAvifPath((string) $this->path);

        if (Storage::disk('public')->exists($avifPath)) {
            return Storage::disk('public')->url($avifPath);
        }

        return null;
    }

    /**
     * Check if AVIF file exists
     */
    public function getHasAvifAttribute(): bool
    {
        return Storage::disk('public')->exists(static::resolveAvifPath((string) $this->path));
    }

    /**
     * Map an original storage path to its AVIF sibling.
     * Nested library folders stay intact: .../orig/a/b.jpg -> .../avif/a/b.avif
     */
    public static function resolveAvifPath(string $origPath): string
    {
        if (! Str::contains($origPath, '/orig/')) {
            $dir = dirname($origPath);
            $filename = pathinfo(basename($origPath), PATHINFO_FILENAME);

            return $dir.'/avif/'.$filename.'.avif';
        }

        $avifPath = Str::replaceFirst('/orig/', '/avif/', $origPath);
        $dir = pathinfo($avifPath, PATHINFO_DIRNAME);
        $base = pathinfo($avifPath, PATHINFO_FILENAME);

        return $dir.'/'.$base.'.avif';
    }

    /**
     * URL for a generated size slug (thumb / medium / large). Prefer AVIF size files.
     */
    public function sizeUrl(string $slug): ?string
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return null;
        }

        if ($this->relationLoaded('variants')) {
            $variant = $this->variants->first(function ($row) use ($slug) {
                $rowSlug = $row->relationLoaded('size')
                    ? ($row->size->slug ?? null)
                    : ($row->size()->value('slug'));

                return $rowSlug === $slug;
            });

            return $variant?->url;
        }

        $variant = $this->variants()
            ->whereHas('size', fn ($q) => $q->where('slug', $slug))
            ->first();

        return $variant?->url;
    }

    /**
     * Lightweight preview for admin grids (thumb → medium → library avif → original).
     */
    public function previewUrl(string $preferred = 'thumb'): string
    {
        foreach (array_unique([$preferred, 'thumb', 'medium']) as $slug) {
            $url = $this->sizeUrl($slug);
            if ($url) {
                return $url;
            }
        }

        return $this->avif_url ?: $this->url;
    }

    /**
     * Check if image can be deleted (no product / category / storefront usage)
     */
    public function canBeDeleted(): bool
    {
        if ($this->assignments()->exists()) {
            return false;
        }

        if (Schema::hasColumn('categories', 'menu_image_id') && $this->menuCategories()->exists()) {
            return false;
        }

        if (isset($this->slides_count) || isset($this->promo_count)) {
            return (int) ($this->slides_count ?? 0) < 1 && (int) ($this->promo_count ?? 0) < 1;
        }

        return ! self::isUsedInStorefrontSettings((int) $this->id);
    }

    public static function isUsedInStorefrontSettings(int $imageId): bool
    {
        if ($imageId < 1 || ! class_exists(\RMS\Core\Models\Setting::class)) {
            return false;
        }

        try {
            foreach (['storefront_hero_slides', 'storefront_home_promo_cards'] as $key) {
                $rows = json_decode((string) (\RMS\Core\Models\Setting::get($key) ?? '[]'), true) ?: [];
                foreach ($rows as $row) {
                    if ((int) ($row['image_id'] ?? 0) === $imageId) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute(): string
    {
        if (!$this->size_bytes) return 'نامشخص';

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->size_bytes;
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
