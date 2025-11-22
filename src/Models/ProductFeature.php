<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFeature extends Model
{
    protected $table = 'product_features';

    protected $fillable = [
        'product_id',
        'category_id',
        'feature_definition_id',
        'feature_value_id',
        'name',
        'value',
        'sort',
    ];

    protected $casts = [
        'sort' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductFeatureCategory::class, 'category_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ProductFeatureDefinition::class, 'feature_definition_id');
    }

    public function definitionValue(): BelongsTo
    {
        return $this->belongsTo(ProductFeatureDefinitionValue::class, 'feature_value_id');
    }
}
