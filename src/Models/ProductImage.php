<?php
// git-trigger
namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $table = 'product_images';

    public $timestamps = true;

    protected $fillable = [
        'product_id', 'path', 'is_main', 'sort'
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'sort' => 'integer',
    ];

    public function product(): BelongsTo { return $this->belongsTo(Product::class, 'product_id'); }
}
