<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAttributeDefinition extends Model
{
    protected $table = 'product_attribute_definitions';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'ui',
        'usage_count',
        'sort',
        'active',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'sort' => 'integer',
        'active' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeDefinitionValue::class, 'attribute_definition_id');
    }
}

