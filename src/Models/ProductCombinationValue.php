<?php
// git-trigger
namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCombinationValue extends Model
{
    protected $table = 'product_combination_values';

    protected $fillable = [
        'combination_id', 'attribute_value_id'
    ];

    public function combination(): BelongsTo { return $this->belongsTo(ProductCombination::class, 'combination_id'); }
    public function value(): BelongsTo { return $this->belongsTo(ProductAttributeValue::class, 'attribute_value_id'); }
}
