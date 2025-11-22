<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFeatureDefinitionValue extends Model
{
    protected $table = 'product_feature_definition_values';

    protected $fillable = [
        'feature_id',
        'value',
        'slug',
        'usage_count',
        'sort',
        'active',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'sort' => 'integer',
        'active' => 'boolean',
    ];

    public function feature(): BelongsTo
    {
        return $this->belongsTo(ProductFeatureDefinition::class, 'feature_id');
    }
}

