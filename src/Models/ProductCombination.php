<?php
// git-trigger
namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductCombination extends Model
{
    protected $table = 'product_combinations';

    protected $fillable = [
        'product_id', 'sku', 'price', 'sale_price', 'sale_price_cny', 'stock_qty', 'active'
    ];

    protected $casts = [
        'active' => 'boolean',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'sale_price_cny' => 'decimal:2',
        'stock_qty' => 'integer',
    ];

    // Relations
    public function product(): BelongsTo { return $this->belongsTo(Product::class, 'product_id'); }
    public function images(): HasMany { return $this->hasMany(ProductCombinationImage::class, 'combination_id'); }
    public function values(): HasMany { return $this->hasMany(ProductCombinationValue::class, 'combination_id')->with(['value.attribute']); }

    // New Image Library Relations
    public function assignedImages() {
        return $this->belongsToMany(ImageLibrary::class, 'image_assignments', 'assignable_id', 'image_id', 'id', 'id')
            ->where('image_assignments.assignable_type', self::class)
            ->withPivot(['is_main', 'sort'])
            ->orderBy('image_assignments.sort');
    }

    public function assignedVideos()
    {
        return $this->belongsToMany(VideoLibrary::class, 'video_assignments', 'assignable_id', 'video_id', 'id', 'id')
            ->where('video_assignments.assignable_type', self::class)
            ->withPivot(['is_main', 'sort'])
            ->orderBy('video_assignments.is_main', 'desc')
            ->orderBy('video_assignments.sort');
    }

    // Image helper
    public function mainImageUrl(): ?string
    {
        // Use assignedImages relationship (Image Library)
        $img = $this->relationLoaded('assignedImages') 
            ? $this->assignedImages->sortByDesc(fn($i) => $i->pivot->is_main ?? 0)->sortBy(fn($i) => $i->pivot->sort ?? 0)->first()
            : $this->assignedImages()->orderByPivot('is_main', 'desc')->orderByPivot('sort')->first();
        
        if (!$img) { return null; }
        
        // Return AVIF URL if available, otherwise regular URL
        return $img->avif_url ?: $img->url;
    }

    public function mainVideoUrl(): ?string
    {
        return $this->assignedVideos->firstWhere('is_main', true)?->video->hls_url;
    }
}
