<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('image_size_variants')) {
            Schema::create('image_size_variants', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('image_id')->constrained('image_library')->cascadeOnDelete();
                $table->foreignId('image_size_id')->constrained('image_sizes')->cascadeOnDelete();
                $table->string('path');
                $table->integer('width');
                $table->integer('height');
                $table->bigInteger('size_bytes')->nullable();
                $table->timestamps();

                $table->unique(['image_id', 'image_size_id'], 'unique_image_size_variant');
                $table->index('image_id');
                $table->index('image_size_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('image_size_variants');
    }
};
