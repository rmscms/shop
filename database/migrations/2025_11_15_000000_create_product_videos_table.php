<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_videos')) {
            Schema::create('product_videos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('title')->nullable();
                $table->string('source_path')->nullable();
                $table->string('hls_master_path')->nullable();
                $table->string('poster_path')->nullable();
                $table->bigInteger('size_bytes')->nullable();
                $table->integer('duration_seconds')->nullable();
                $table->integer('sort')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_videos');
    }
};

