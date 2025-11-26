<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'brand_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('brand_id')
                    ->nullable()
                    ->after('category_id')
                    ->constrained('shop_brands')
                    ->restrictOnDelete();
            });
        }

        $defaultBrand = DB::table('shop_brands')->where('slug', 'default-brand')->first();

        if (!$defaultBrand) {
            $brandId = DB::table('shop_brands')->insertGetId([
                'name' => 'برند پیش‌فرض',
                'slug' => 'default-brand',
                'description' => 'برند پیش‌فرض برای محصولات موجود.',
                'is_active' => true,
                'sort' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $brandId = $defaultBrand->id;
        }

        if (!empty($brandId)) {
            DB::table('products')
                ->whereNull('brand_id')
                ->update(['brand_id' => $brandId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('products', 'brand_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['brand_id']);
                $table->dropColumn('brand_id');
            });
        }
    }
};

