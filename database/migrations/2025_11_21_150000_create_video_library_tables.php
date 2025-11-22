<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_library', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('path');
            $table->string('hls_path')->nullable();
            $table->string('poster_path')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->timestamps();
        });

        Schema::create('video_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id');
            $table->morphs('assignable');
            $table->boolean('is_main')->default(false);
            $table->integer('sort')->default(0);
            $table->timestamps();
            
            $table->foreign('video_id')->references('id')->on('video_library')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_assignments');
        Schema::dropIfExists('video_library');
    }
};

