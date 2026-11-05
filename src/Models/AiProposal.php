<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use RMS\Shop\Models\Concerns\HasRound;

/**
 * A change an agent proposes for any shop resource (product, category, brand ...).
 * Nothing touches the resource until an admin approves it.
 */
class AiProposal extends Model
{
    use HasRound;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_FAILED = 'failed';

    protected $table = 'shop_ai_proposals';

    protected $fillable = [
        'resource_type', 'resource_id', 'status', 'access_id', 'actor', 'source', 'note',
        'changes', 'before', 'applied_fields', 'error', 'reviewed_by', 'applied_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'before' => 'array',
        'applied_fields' => 'array',
        'applied_at' => 'datetime',
    ];
}
