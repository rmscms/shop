<?php
// git-trigger
namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductCombination;
use RMS\Shop\Models\ImageAssignment;

class ImageLibrary extends Model
{
    protected $table = 'image_library';

    public $timestamps = true;

    protected $fillable = [
        'filename',
        'path',
        'size_bytes',
        'mime_type',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    /**
     * Get all assignments for this image (products, combinations)
     */
    public function assignments()
    {
        return $this->hasMany(ImageAssignment::class, 'image_id');
    }

    /**
     * Get products this image is assigned to
     */
    public function products()
    {
        return $this->morphedByMany(Product::class, 'assignable', 'image_assignments')
            ->withPivot(['is_main', 'sort']);
    }

    /**
     * Get product combinations this image is assigned to
     */
    public function productCombinations()
    {
        return $this->morphedByMany(ProductCombination::class, 'assignable', 'image_assignments')
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
        // Path format: uploads/products/library/orig/filename.jpg
        // AVIF should be: uploads/products/library/avif/filename.avif
        
        if (Str::contains($this->path, '/orig/')) {
            $baseDir = Str::beforeLast($this->path, '/orig/');
            $filename = pathinfo($this->filename, PATHINFO_FILENAME);
            $avifPath = $baseDir . '/avif/' . $filename . '.avif';
        } else {
            // Fallback for other structures
            $dir = dirname($this->path);
            $filename = pathinfo($this->filename, PATHINFO_FILENAME);
            $avifPath = $dir . '/avif/' . $filename . '.avif';
        }

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
        // Path format: uploads/products/library/orig/filename.jpg
        // AVIF should be: uploads/products/library/avif/filename.avif
        
        if (Str::contains($this->path, '/orig/')) {
            $baseDir = Str::beforeLast($this->path, '/orig/');
            $filename = pathinfo($this->filename, PATHINFO_FILENAME);
            $avifPath = $baseDir . '/avif/' . $filename . '.avif';
        } else {
            // Fallback for other structures
            $dir = dirname($this->path);
            $filename = pathinfo($this->filename, PATHINFO_FILENAME);
            $avifPath = $dir . '/avif/' . $filename . '.avif';
        }

        return Storage::disk('public')->exists($avifPath);
    }

    /**
     * Check if image can be deleted (no assignments)
     */
    public function canBeDeleted(): bool
    {
        return $this->assignments->count() === 0;
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
