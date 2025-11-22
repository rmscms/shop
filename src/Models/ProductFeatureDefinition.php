<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductFeatureDefinition extends Model
{
    protected $table = 'product_feature_definitions';

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'usage_count',
        'sort',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'usage_count' => 'integer',
        'sort' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductFeatureCategory::class, 'category_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductFeatureDefinitionValue::class, 'feature_id');
    }
}

