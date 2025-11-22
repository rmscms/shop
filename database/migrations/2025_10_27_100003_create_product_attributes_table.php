<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_attributes')) {
            Schema::create('product_attributes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('name');
                $table->string('type')->nullable(); // color, size, material, etc.
                $table->string('ui')->nullable(); // pill, dropdown, button, etc.
                $table->integer('sort')->default(0);
                $table->timestamps();
                $table->unique(['product_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
    }
};

