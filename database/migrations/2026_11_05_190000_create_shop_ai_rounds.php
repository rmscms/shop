<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables whose rows belong to a work round. */
    private const TABLES = ['shop_ai_reviews', 'shop_ai_proposals', 'shop_ai_suggestions', 'shop_ai_activities', 'shop_ai_actions'];

    public function up(): void
    {
        if (! Schema::hasTable('shop_ai_rounds')) {
            Schema::create('shop_ai_rounds', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 120)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('started_at');
                // null while the round is the current one
                $table->timestamp('ended_at')->nullable();
                $table->unsignedBigInteger('started_by')->nullable();
                $table->timestamps();
            });
        }

        foreach (self::TABLES as $name) {
            if (Schema::hasTable($name) && ! Schema::hasColumn($name, 'round_id')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->unsignedBigInteger('round_id')->nullable()->index();
                });
            }
        }

        // Everything that exists today is the first round.
        if (DB::table('shop_ai_rounds')->count() === 0) {
            $first = now();
            foreach (self::TABLES as $name) {
                if (Schema::hasTable($name)) {
                    $min = DB::table($name)->min('created_at');
                    if ($min && $min < $first) {
                        $first = \Illuminate\Support\Carbon::parse($min);
                    }
                }
            }
            $id = DB::table('shop_ai_rounds')->insertGetId([
                'name' => null, 'note' => null, 'started_at' => $first, 'ended_at' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (self::TABLES as $name) {
                if (Schema::hasTable($name) && Schema::hasColumn($name, 'round_id')) {
                    DB::table($name)->whereNull('round_id')->update(['round_id' => $id]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasTable($name) && Schema::hasColumn($name, 'round_id')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropColumn('round_id');
                });
            }
        }
        Schema::dropIfExists('shop_ai_rounds');
    }
};
