<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('image_sizes')) {
            Schema::create('image_sizes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('slug')->unique();
                $table->integer('width')->nullable();
                $table->integer('height')->nullable();
                $table->integer('quality')->default(85);
                $table->boolean('active')->default(true);
                $table->integer('sort')->default(0);
                $table->timestamps();

                $table->index('active');
                $table->index('sort');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('image_sizes');
    }
};
