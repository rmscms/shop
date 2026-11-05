<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_api_accesses')) {
            return;
        }

        if (! Schema::hasColumn('shop_api_accesses', 'permission')) {
            Schema::table('shop_api_accesses', function (Blueprint $table) {
                // read: GET only. read_write: can also create/update/delete.
                // Existing tokens could already write, so they keep that; new ones are created as read.
                $table->string('permission', 20)->default('read_write');
            });
        }

        if (Schema::hasColumn('shop_api_accesses', 'can_apply')) {
            Schema::table('shop_api_accesses', function (Blueprint $table) {
                $table->dropColumn('can_apply');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shop_api_accesses') && Schema::hasColumn('shop_api_accesses', 'permission')) {
            Schema::table('shop_api_accesses', function (Blueprint $table) {
                $table->dropColumn('permission');
            });
        }
    }
};
