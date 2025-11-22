<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAdminNote extends Model
{
    protected $table = 'order_admin_notes';

    protected $fillable = [
        'order_id',
        'admin_id',
        'note_text',
        'visible_to_user',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'admin_id' => 'integer',
        'visible_to_user' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
