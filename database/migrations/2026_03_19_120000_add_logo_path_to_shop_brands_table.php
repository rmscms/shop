<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_brands') && ! Schema::hasColumn('shop_brands', 'logo_path')) {
            Schema::table('shop_brands', function (Blueprint $table) {
                $table->string('logo_path', 500)->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shop_brands') && Schema::hasColumn('shop_brands', 'logo_path')) {
            Schema::table('shop_brands', function (Blueprint $table) {
                $table->dropColumn('logo_path');
            });
        }
    }
};
