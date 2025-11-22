<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_addresses')) {
            return;
        }

        Schema::table('user_addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('user_addresses', 'province_id')) {
                $table->unsignedTinyInteger('province_id')->nullable()->after('phone');
                $table->index('province_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_addresses')) {
            return;
        }

        Schema::table('user_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('user_addresses', 'province_id')) {
                $table->dropIndex('user_addresses_province_id_index');
                $table->dropColumn('province_id');
            }
        });
    }
};

