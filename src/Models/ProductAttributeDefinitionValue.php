<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttributeDefinitionValue extends Model
{
    protected $table = 'product_attribute_definition_values';

    protected $fillable = [
        'attribute_definition_id',
        'value',
        'slug',
        'image_path',
        'color',
        'usage_count',
        'sort',
        'active',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'sort' => 'integer',
        'active' => 'boolean',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeDefinition::class, 'attribute_definition_id');
    }
}

