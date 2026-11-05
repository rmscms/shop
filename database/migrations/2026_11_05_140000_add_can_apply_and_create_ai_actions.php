<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_api_accesses') && ! Schema::hasColumn('shop_api_accesses', 'can_apply')) {
            Schema::table('shop_api_accesses', function (Blueprint $table) {
                // Off by default: a token only suggests until an admin turns this on.
                $table->boolean('can_apply')->default(false);
            });
        }

        if (! Schema::hasTable('shop_ai_actions')) {
            Schema::create('shop_ai_actions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedBigInteger('access_id')->nullable()->index();
                $table->string('source', 100)->nullable();
                $table->json('fields')->nullable();
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                // The admin's words that authorised this change, as the agent quotes them.
                $table->text('operator_instruction');
                $table->unsignedBigInteger('suggestion_id')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_ai_actions');
        if (Schema::hasTable('shop_api_accesses') && Schema::hasColumn('shop_api_accesses', 'can_apply')) {
            Schema::table('shop_api_accesses', function (Blueprint $table) {
                $table->dropColumn('can_apply');
            });
        }
    }
};
