<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_ai_reviews')) {
            return;
        }

        Schema::create('shop_ai_reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // requested, done, dismissed
            $table->string('status', 20)->default('requested')->index();
            $table->json('aspects')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->json('result')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('source', 100)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_ai_reviews');
    }
};
