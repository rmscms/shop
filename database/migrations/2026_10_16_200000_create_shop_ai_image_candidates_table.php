<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_ai_image_candidates')) {
            return;
        }

        // Photos found for a product (or a staged catalog row before its product exists).
        // Nothing is downloaded until an admin picks it.
        Schema::create('shop_ai_image_candidates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('catalog_item_id')->nullable()->index();
            $table->string('url', 1000);
            $table->string('source_url', 1000)->nullable();
            $table->string('title', 300)->nullable();
            // candidate, attached, rejected
            $table->string('status', 20)->default('candidate')->index();
            $table->unsignedBigInteger('image_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_ai_image_candidates');
    }
};
