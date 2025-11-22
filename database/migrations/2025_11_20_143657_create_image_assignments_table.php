<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('image_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('image_id')->constrained('image_library')->cascadeOnDelete();
            $table->morphs('assignable'); // assignable_type, assignable_id (Product یا ProductCombination)
            $table->boolean('is_main')->default(false);
            $table->integer('sort')->default(0);
            $table->timestamps();

            // Indexes
            $table->unique(['image_id', 'assignable_type', 'assignable_id'], 'unique_image_assignment');
            $table->index('is_main');
            $table->index('sort');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_assignments');
    }
};
