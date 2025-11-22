<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_admin_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('order_admin_notes', 'admin_id')) {
                $table->unsignedBigInteger('admin_id')->nullable()->after('order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_admin_notes', function (Blueprint $table) {
            if (Schema::hasColumn('order_admin_notes', 'admin_id')) {
                $table->dropColumn('admin_id');
            }
        });
    }
};

