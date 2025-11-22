<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_combination_values')) {
            Schema::create('product_combination_values', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('combination_id')->constrained('product_combinations')->cascadeOnDelete();
                $table->foreignId('attribute_value_id')->constrained('product_attribute_values')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['combination_id', 'attribute_value_id'], 'pcv_comb_attr_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_combination_values');
    }
};

