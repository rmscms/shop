<?php
// git-trigger
namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'name', 'slug', 'sku', 'price', 'sale_price', 'active', 'stock_qty', 'category_id', 'brand_id',
        'short_desc', 'description', 'cost_cny', 'sale_price_cny', 'point_per_unit',
        'discount_type', 'discount_value',
    ];

    protected $casts = [
        'active' => 'boolean',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost_cny' => 'decimal:2',
        'sale_price_cny' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'stock_qty' => 'integer',
        'point_per_unit' => 'integer',
    ];

    // Relations
    public function category(): BelongsTo { return $this->belongsTo(Category::class, 'category_id'); }
    public function images(): HasMany { return $this->hasMany(ProductImage::class, 'product_id'); }
    public function combinations(): HasMany { return $this->hasMany(ProductCombination::class, 'product_id'); }
    public function videos(): HasMany { return $this->hasMany(ProductVideo::class, 'product_id'); }
    public function attributes(): HasMany { return $this->hasMany(ProductAttribute::class, 'product_id'); }
    public function attributeValues(): HasManyThrough { return $this->hasManyThrough(ProductAttributeValue::class, ProductAttribute::class, 'product_id', 'attribute_id'); }
    public function combinationImages(): HasManyThrough { return $this->hasManyThrough(ProductCombinationImage::class, ProductCombination::class, 'product_id', 'combination_id', 'id', 'id'); }

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

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    // Custom renderer for admin list thumbnail (AVIF + fallback)
    public function renderListThumb(): string
    {
        $row = $this->images()
            ->orderByDesc('is_main')
            ->orderBy('sort')
            ->first();
        if (!$row) { return ''; }
        $rel = (string)$row->path; // uploads/products/.../orig/uuid.jpg
        $dir = Str::beforeLast($rel, '/orig/');
        $name = Str::afterLast($rel, '/orig/');
        $base = pathinfo($name, PATHINFO_FILENAME);
        $avifRel = $dir.'/avif/'.$base.'.avif';
        $urlOrig = e(Storage::disk('public')->url($rel));
        $urlAvif = Storage::disk('public')->exists($avifRel) ? e(Storage::disk('public')->url($avifRel)) : null;
        $alt = e($this->name ?? '');
        $img = $urlAvif ? "<picture><source srcset=\"$urlAvif\" type=\"image/avif\"><img src=\"$urlOrig\" alt=\"$alt\" loading=\"lazy\" decoding=\"async\" style=\"width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--bs-border-color);\"></picture>"
                         : "<img src=\"$urlOrig\" alt=\"$alt\" loading=\"lazy\" decoding=\"async\" style=\"width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--bs-border-color);\">";
        return $img;
    }

    // ===== Image helpers =====
    public function mainImageUrl(): ?string
    {
        // Prefer loaded relation to avoid extra queries
        $img = $this->relationLoaded('images') ? $this->images->sortByDesc('is_main')->sortBy('sort')->first() : $this->images()->orderByDesc('is_main')->orderBy('sort')->first();
        if (!$img) { return null; }
        $rel = (string)$img->path;
        if (!Storage::disk('public')->exists($rel)) { return null; }
        $dir = Str::beforeLast($rel, '/orig/');
        $name = Str::afterLast($rel, '/orig/');
        $base = pathinfo($name, PATHINFO_FILENAME);
        $avifRel = $dir.'/avif/'.$base.'.avif';
        return Storage::disk('public')->exists($avifRel)
            ? Storage::disk('public')->url($avifRel)
            : Storage::disk('public')->url($rel);
    }

    // ===== Read-only helpers (for controller) =====
    /**
     * Map of combination_id => label like "SKU — Attr: Val / Attr2: Val2"
     */
    public function comboLabels(): array
    {
        $repo = static::cacheRepoForId((int)$this->id);
        $cacheKey = 'shop:product:combo-labels:'.(int)$this->id;
        $cached = $repo->get($cacheKey);
        if (is_array($cached)) { return $cached; }
        $this->loadMissing(['combinations.values.value.attribute']);
        $labels = [];
        foreach ($this->combinations as $comb) {
            $parts = [];
            foreach ($comb->values as $cv) {
                $attr = $cv->value->attribute->name ?? null;
                $val = $cv->value->value ?? null;
                if ($attr !== null && $val !== null) { $parts[] = $attr.': '.$val; }
            }
            $label = ($comb->sku ? ($comb->sku.' — ') : '').implode(' / ', $parts);
            $labels[$comb->id] = $label;
        }
        $repo->put($cacheKey, $labels, 600);
        return $labels;
    }

    /** Map of combination_id => image_count */
    public function imageCountsByCombination(): array
    {
        $repo = static::cacheRepoForId((int)$this->id);
        $cacheKey = 'shop:product:image-counts:'.(int)$this->id;
        $cached = $repo->get($cacheKey);
        if (is_array($cached)) { return $cached; }
        
        // Get image counts via ImageAssignment table
        $combinationIds = $this->combinations()->pluck('id')->toArray();
        
        $counts = \RMS\Shop\Models\ImageAssignment::query()
            ->where('assignable_type', \RMS\Shop\Models\ProductCombination::class)
            ->whereIn('assignable_id', $combinationIds)
            ->select('assignable_id', \DB::raw('COUNT(*) as count'))
            ->groupBy('assignable_id')
            ->pluck('count', 'assignable_id')
            ->toArray();
        
        // Initialize all combinations with 0
        $out = [];
        foreach ($combinationIds as $combId) {
            $out[$combId] = $counts[$combId] ?? 0;
        }
        
        $repo->put($cacheKey, $out, 600);
        return $out;
    }

    /** Map of image path => [combination_id,...] */
    public function assignedImagesMap(): array
    {
        $this->loadMissing(['combinations.images']);
        $map = [];
        foreach ($this->combinations as $c) {
            foreach ($c->images as $img) {
                $map[$img->path] = $map[$img->path] ?? [];
                $map[$img->path][] = (int)$c->id;
            }
        }
        return $map;
    }

    // ===== Availability (cached) =====
    protected static function availabilityCacheKey(int $productId): string
    {
        return 'shop:product:availability:'.$productId;
    }

    protected static function cacheRepoForId(int $productId)
    {
        try {
            // Prefer tagged cache if supported by driver
            return \Cache::tags(['shop:product', 'product:'.$productId]);
        } catch (\Throwable $e) {
            return \Cache::store(); // default repository
        }
    }

    public static function invalidateAvailabilityCache(int $productId): void
    {
        $repo = static::cacheRepoForId($productId);
        $repo->forget(static::availabilityCacheKey($productId));
    }

    public static function invalidateComboLabelsCache(int $productId): void
    {
        $repo = static::cacheRepoForId($productId);
        $repo->forget('shop:product:combo-labels:'.$productId);
    }

    public static function invalidateImageCountsCache(int $productId): void
    {
        $repo = static::cacheRepoForId($productId);
        $repo->forget('shop:product:image-counts:'.$productId);
    }

    public static function refreshAvailabilityCache(int $productId): array
    {
        $data = static::computeAvailability($productId);
        $repo = static::cacheRepoForId($productId);
        // Persist forever; runtime method may use TTL remember
        $repo->forever(static::availabilityCacheKey($productId), $data);
        return $data;
    }

    public function availability(?array $prefetched = null, int $ttlSeconds = 900): array
    {
        $id = (int)$this->id;
        if ($prefetched) {
            return static::formatAvailability($prefetched['product_stock'] ?? 0, $prefetched['comb_stock'] ?? 0);
        }
        // Use cached summary if present; otherwise compute and cache with TTL
        $key = static::availabilityCacheKey($id);
        $repo = static::cacheRepoForId($id);
        $cached = $repo->get($key);
        if (is_array($cached)) { return $cached; }
        $computed = static::computeAvailability($id);
        $repo->put($key, $computed, $ttlSeconds);
        return $computed;
    }

    public static function availabilityForId(int $productId, int $ttlSeconds = 900): array
    {
        $key = static::availabilityCacheKey((int)$productId);
        $repo = static::cacheRepoForId((int)$productId);
        $cached = $repo->get($key);
        if (is_array($cached)) { return $cached; }
        $computed = static::computeAvailability((int)$productId);
        $repo->put($key, $computed, $ttlSeconds);
        return $computed;
    }

    protected static function computeAvailability(int $productId): array
    {
        $baseStock = (int) (DB::table('products')->where('id', $productId)->value('stock_qty') ?? 0);
        $combStock = (int) DB::table('product_combinations')
            ->where('product_id', $productId)
            ->where('active', 1)
            ->sum('stock_qty');
        return static::formatAvailability($baseStock, $combStock);
    }

    protected static function formatAvailability(int $baseStock, int $combStock): array
    {
        $total = max(0, $baseStock) + max(0, $combStock);
        if ($baseStock > 0) {
            return [
                'status' => 'available',
                'label' => 'موجود',
                'badge_class' => 'success',
                'product_stock' => $baseStock,
                'comb_stock' => $combStock,
                'total_stock' => $total,
            ];
        }
        if ($combStock > 0) {
            return [
                'status' => 'available_variant',
                'label' => 'موجود (ترکیب دیگر)',
                'badge_class' => 'info',
                'product_stock' => $baseStock,
                'comb_stock' => $combStock,
                'total_stock' => $total,
            ];
        }
        return [
            'status' => 'unavailable',
            'label' => 'ناموجود',
            'badge_class' => 'danger',
            'product_stock' => $baseStock,
            'comb_stock' => $combStock,
            'total_stock' => $total,
        ];
    }
}
