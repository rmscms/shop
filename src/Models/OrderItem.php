<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RMS\Shop\Models\ImageLibrary;

class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'combination_id',
        'image_id',
        'image_snapshot',
        'qty',
        'unit_price',
        'total',
        'unit_price_cny',
        'rate_cny_to_irt',
        'points_awarded',
        'item_name',
        'item_attributes',
    ];

    protected $casts = [
        'image_snapshot' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function combination(): BelongsTo
    {
        return $this->belongsTo(ProductCombination::class, 'combination_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(ImageLibrary::class, 'image_id');
    }

    /**
     * Resolve stored image snapshot into absolute URLs.
     */
    public function snapshotImageUrls(): array
    {
        $snapshot = $this->image_snapshot ?? [];
        $disk = $snapshot['disk'] ?? 'public';
        $path = $snapshot['path'] ?? null;
        $avifPath = $snapshot['avif_path'] ?? null;

        if (!$path && $this->relationLoaded('image') && $this->image) {
            $path = $this->image->path;
        }

        if (!$path && $this->image_id) {
            $path = $this->image()->value('path');
        }

        if (!$path) {
            return [
                'url' => $this->product?->mainImageUrl(),
                'avif_url' => null,
            ];
        }

        $url = self::urlIfExists($disk, $path);

        if (!$avifPath) {
            $avifPath = self::deriveAvifPath($path);
        }

        $avifUrl = $avifPath ? self::urlIfExists($disk, $avifPath) : null;

        if (!$url) {
            $url = $this->product?->mainImageUrl();
        }

        return [
            'url' => $url,
            'avif_url' => $avifUrl,
        ];
    }

    protected static function deriveAvifPath(?string $relativePath): ?string
    {
        if (!$relativePath || !Str::contains($relativePath, '/orig/')) {
            return null;
        }

        $directory = Str::beforeLast($relativePath, '/orig/');
        $filename = Str::afterLast($relativePath, '/orig/');
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        return $directory.'/avif/'.$baseName.'.avif';
    }

    protected static function urlIfExists(string $disk, string $path): ?string
    {
        $diskInstance = Storage::disk($disk);
        if (!$diskInstance->exists($path)) {
            return null;
        }

        return $diskInstance->url($path);
    }
}