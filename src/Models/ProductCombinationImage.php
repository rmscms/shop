<?php
// git-trigger
namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCombinationImage extends Model
{
    protected $table = 'product_combination_images';

    protected $fillable = [
        'combination_id', 'path', 'is_main', 'sort'
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'sort' => 'integer',
    ];

    public function combination(): BelongsTo { return $this->belongsTo(ProductCombination::class, 'combination_id'); }
}
