<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('shop_brands')) {
            Schema::create('shop_brands', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort')->default(0);
                $table->timestamps();

                $table->index('is_active');
                $table->index('sort');
            });

            DB::table('shop_brands')->insert([
                'name' => 'برند پیش‌فرض',
                'slug' => Str::slug('برند پیش‌فرض') ?: 'default-brand',
                'description' => 'برند پیش‌فرض برای محصولات موجود.',
                'is_active' => true,
                'sort' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_brands');
    }
};

