<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->foreignId('combination_id')->nullable()->constrained('product_combinations')->nullOnDelete();
                $table->integer('qty');
                $table->decimal('unit_price', 18, 2);
                $table->decimal('total', 18, 2);
                $table->decimal('unit_price_cny', 18, 2)->nullable();
                $table->decimal('rate_cny_to_irt', 18, 6)->nullable();
                $table->integer('points_awarded')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

