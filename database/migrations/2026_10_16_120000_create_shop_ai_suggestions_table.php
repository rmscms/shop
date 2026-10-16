<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_ai_suggestions')) {
            return;
        }

        Schema::create('shop_ai_suggestions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // pending, processing, ready, failed, applied, rejected
            $table->string('status', 20)->default('pending')->index();
            $table->string('driver', 30)->nullable();
            $table->string('model', 100)->nullable();
            $table->json('payload')->nullable();
            $table->json('applied_fields')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('images_sent')->default(0);
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_ai_suggestions');
    }
};
