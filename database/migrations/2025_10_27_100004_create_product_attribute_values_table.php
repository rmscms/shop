<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_attribute_values')) {
            Schema::create('product_attribute_values', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('attribute_id')->constrained('product_attributes')->cascadeOnDelete();
                $table->string('value');
                $table->string('image_path')->nullable();
                $table->string('color')->nullable(); // hex color for color attributes
                $table->integer('sort')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
    }
};

