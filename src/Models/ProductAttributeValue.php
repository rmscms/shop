<?php
// git-trigger
namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttributeValue extends Model
{
    protected $table = 'product_attribute_values';

    protected $fillable = [
        'attribute_id',
        'definition_value_id',
        'value',
        'image_path',
        'color',
        'sort',
    ];

    protected $casts = [
        'sort' => 'integer',
        'color' => 'string',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'attribute_id');
    }

    public function definitionValue(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeDefinitionValue::class, 'definition_value_id');
    }
}
