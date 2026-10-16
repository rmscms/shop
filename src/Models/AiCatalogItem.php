<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class AiCatalogItem extends Model
{
    public const STATUS_STAGED = 'staged';
    public const STATUS_IMPORTED = 'imported';

    protected $table = 'shop_ai_catalog_items';

    protected $fillable = [
        'batch',
        'row',
        'name',
        'spec',
        'raw',
        'status',
        'product_id',
    ];

    protected $casts = [
        'row' => 'integer',
        'raw' => 'array',
        'product_id' => 'integer',
    ];

    public function label(): string
    {
        return trim($this->name.' '.($this->spec ?? ''));
    }
}
