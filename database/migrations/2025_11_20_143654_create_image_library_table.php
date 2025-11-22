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
        Schema::create('image_library', function (Blueprint $table) {
            $table->id();
            $table->string('filename'); // نام فایل اصلی
            $table->string('path'); // مسیر نسبی در storage (uploads/products/library/filename)
            $table->unsignedBigInteger('size_bytes')->nullable(); // اندازه فایل در بایت
            $table->string('mime_type')->nullable(); // نوع فایل
            $table->timestamps();

            // Indexes
            $table->index('filename');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_library');
    }
};
