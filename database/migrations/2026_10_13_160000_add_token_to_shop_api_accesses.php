<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shop_api_accesses')) {
            return;
        }

        Schema::table('shop_api_accesses', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_api_accesses', 'token')) {
                $table->text('token')->nullable()->after('token_hash');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('shop_api_accesses') || !Schema::hasColumn('shop_api_accesses', 'token')) {
            return;
        }

        Schema::table('shop_api_accesses', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }
};
