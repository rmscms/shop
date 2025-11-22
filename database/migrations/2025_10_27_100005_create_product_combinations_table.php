<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_combinations')) {
            Schema::create('product_combinations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('sku')->nullable()->unique();
                $table->decimal('price', 18, 2)->nullable();
                $table->decimal('sale_price', 18, 2)->nullable();
                $table->decimal('sale_price_cny', 18, 2)->nullable();
                $table->integer('stock_qty')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_combinations');
    }
};

