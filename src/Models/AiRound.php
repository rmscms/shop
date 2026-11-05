<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A work round of the AI agents. Starting a new round archives everything done so far
 * (reviews, proposals, activity); archived rounds stay readable on the AI activity page.
 */
class AiRound extends Model
{
    protected $table = 'shop_ai_rounds';

    protected $fillable = ['name', 'note', 'started_at', 'ended_at', 'started_by'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static ?array $memo = null;

    public static function available(): bool
    {
        return static::$memo['available'] ??= Schema::hasTable('shop_ai_rounds');
    }

    /** The round new work belongs to (null before the migration ran). */
    public static function current(): ?self
    {
        if (! static::available()) {
            return null;
        }
        if (! array_key_exists('current', static::$memo)) {
            static::$memo['current'] = static::query()->whereNull('ended_at')->orderByDesc('id')->first()
                ?? static::query()->create(['started_at' => now()]);
        }

        return static::$memo['current'];
    }

    public static function currentId(): ?int
    {
        return static::current()?->id;
    }

    public static function forget(): void
    {
        static::$memo = null;
    }

    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }

    public function label(): string
    {
        return $this->name ?: 'دور '.$this->id;
    }

    /**
     * Close the current round and open a new one. Work that is still open (requested reviews,
     * pending proposals, suggestions waiting for a decision) moves along so nothing gets stuck in the archive.
     */
    public static function startNew(?string $name = null, ?int $adminId = null, ?string $note = null): self
    {
        return DB::transaction(function () use ($name, $adminId, $note) {
            $old = static::current();
            $old?->forceFill(['ended_at' => now()])->save();

            $new = static::query()->create([
                'name' => $name ? trim($name) : null,
                'note' => $note ? trim($note) : null,
                'started_at' => now(),
                'started_by' => $adminId,
            ]);

            if (Schema::hasTable('shop_ai_reviews')) {
                DB::table('shop_ai_reviews')->where('status', 'requested')->update(['round_id' => $new->id]);
            }
            if (Schema::hasTable('shop_ai_proposals')) {
                DB::table('shop_ai_proposals')->where('status', 'pending')->update(['round_id' => $new->id]);
            }
            if (Schema::hasTable('shop_ai_suggestions')) {
                DB::table('shop_ai_suggestions')->whereIn('status', ['pending', 'processing', 'ready'])->update(['round_id' => $new->id]);
            }

            static::forget();

            return $new;
        });
    }
}
