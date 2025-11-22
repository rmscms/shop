<?php
// git-trigger
namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ProductRating extends Model
{
    protected $table = 'product_ratings';

    protected $fillable = [
        'product_id', 'user_id', 'rating', 'title', 'body'
    ];
}
