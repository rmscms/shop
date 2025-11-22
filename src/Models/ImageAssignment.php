<?php
// git-trigger
namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageAssignment extends Model
{
    protected $table = 'image_assignments';

    public $timestamps = true;

    protected $fillable = [
        'image_id',
        'assignable_type',
        'assignable_id',
        'is_main',
        'sort',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'sort' => 'integer',
    ];

    /**
     * Get the image this assignment belongs to
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(ImageLibrary::class, 'image_id');
    }

    /**
     * Get the owning assignable model (Product or ProductCombination)
     */
    public function assignable(): MorphTo
    {
        return $this->morphTo('assignable');
    }
}
