<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cart_items')) {
            Schema::create('cart_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('combination_id')->nullable()->constrained('product_combinations')->nullOnDelete();
                $table->integer('qty');
                $table->decimal('unit_price', 18, 2);
                $table->decimal('unit_price_cny', 18, 2)->nullable();
                $table->timestamps();
                $table->unique(['cart_id', 'product_id', 'combination_id'], 'cart_items_unique_line');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};

