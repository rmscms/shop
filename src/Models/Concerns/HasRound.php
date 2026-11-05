<?php

namespace RMS\Shop\Models\Concerns;

use Illuminate\Support\Facades\Schema;
use RMS\Shop\Models\AiRound;

/** New rows join the current AI work round (skipped until the round_id column exists). */
trait HasRound
{
    /** Remembered only once the column exists, so a long-running process notices the migration. */
    protected static array $roundColumnReady = [];

    protected static function bootHasRound(): void
    {
        static::creating(function ($model) {
            $table = $model->getTable();
            if (empty(static::$roundColumnReady[$table])) {
                static::$roundColumnReady[$table] = Schema::hasColumn($table, 'round_id');
            }
            if (static::$roundColumnReady[$table] && empty($model->getAttribute('round_id'))) {
                $model->setAttribute('round_id', AiRound::currentId());
            }
        });
    }
}
