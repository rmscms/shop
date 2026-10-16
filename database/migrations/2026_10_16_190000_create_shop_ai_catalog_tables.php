<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Raw rows from an imported product list, kept until a catalog plan turns them into products.
        if (!Schema::hasTable('shop_ai_catalog_items')) {
            Schema::create('shop_ai_catalog_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('batch', 100)->index();
                $table->unsignedInteger('row');
                $table->string('name', 500);
                $table->string('spec', 500)->nullable();
                $table->json('raw')->nullable();
                // staged, imported
                $table->string('status', 20)->default('staged')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->timestamps();

                $table->unique(['batch', 'row']);
            });
        }

        if (!Schema::hasTable('shop_ai_catalog_plans')) {
            Schema::create('shop_ai_catalog_plans', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('batch', 100)->index();
                // pending, processing, ready, failed, applied, rejected
                $table->string('status', 20)->default('pending')->index();
                $table->string('driver', 30)->nullable();
                $table->string('model', 100)->nullable();
                $table->json('payload')->nullable();
                $table->json('result')->nullable();
                $table->text('error')->nullable();
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_ai_catalog_plans');
        Schema::dropIfExists('shop_ai_catalog_items');
    }
};
