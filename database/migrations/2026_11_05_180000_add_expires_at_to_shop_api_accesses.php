<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_api_accesses') && ! Schema::hasColumn('shop_api_accesses', 'expires_at')) {
            Schema::table('shop_api_accesses', function (Blueprint $table) {
                // null = never expires (all tokens made before this column keep working).
                $table->timestamp('expires_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shop_api_accesses') && Schema::hasColumn('shop_api_accesses', 'expires_at')) {
            Schema::table('shop_api_accesses', function (Blueprint $table) {
                $table->dropColumn('expires_at');
            });
        }
    }
};
