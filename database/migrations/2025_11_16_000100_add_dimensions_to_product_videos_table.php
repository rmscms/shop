<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_videos')) {
            Schema::table('product_videos', function (Blueprint $table) {
                if (!Schema::hasColumn('product_videos', 'width')) {
                    $table->integer('width')->nullable()->after('size_bytes');
                }
                if (!Schema::hasColumn('product_videos', 'height')) {
                    $table->integer('height')->nullable()->after('width');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_videos')) {
            Schema::table('product_videos', function (Blueprint $table) {
                if (Schema::hasColumn('product_videos', 'width')) {
                    $table->dropColumn('width');
                }
                if (Schema::hasColumn('product_videos', 'height')) {
                    $table->dropColumn('height');
                }
            });
        }
    }
};

