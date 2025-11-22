<?php
// git-trigger
namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAttribute extends Model
{
    protected $table = 'product_attributes';

    protected $fillable = [
        'product_id',
        'attribute_definition_id',
        'name',
        'sort',
        'type',
        'ui',
    ];

    protected $casts = [
        'sort' => 'integer',
        'type' => 'string',
        'ui' => 'string',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class, 'attribute_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeDefinition::class, 'attribute_definition_id');
    }
}
