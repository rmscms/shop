<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('sku')->nullable()->unique();
                $table->decimal('price', 18, 2)->nullable();
                $table->decimal('sale_price', 18, 2)->nullable();
                $table->boolean('active')->default(true);
                $table->integer('stock_qty')->default(0);
                $table->text('short_desc')->nullable();
                $table->text('description')->nullable();
                $table->decimal('cost_cny', 18, 2)->nullable();
                $table->decimal('sale_price_cny', 18, 2)->nullable();
                $table->string('discount_type')->nullable();
                $table->decimal('discount_value', 18, 2)->nullable();
                $table->integer('point_per_unit')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

