<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_avif_directories')) {
            return;
        }

        Schema::create('shop_avif_directories', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->enum('type', ['public', 'storage'])->default('public');
            $table->boolean('active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['path', 'type'], 'shop_avif_directories_path_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_avif_directories');
    }
};

