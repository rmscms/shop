<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductFeatureCategory extends Model
{
    use HasFactory;

    protected $table = 'product_feature_categories';

    protected $fillable = [
        'name',
        'slug', 
        'icon',
        'description',
        'sort',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort' => 'integer',
    ];

    public function features()
    {
        return $this->hasMany(\RMS\Shop\Models\ProductFeature::class, 'category_id');
    }
}