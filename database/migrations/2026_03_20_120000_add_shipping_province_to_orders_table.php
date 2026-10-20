<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'shipping_province_id')) {
                $table->unsignedTinyInteger('shipping_province_id')->nullable()->after('shipping_name');
                $table->index('shipping_province_id');
            }
            if (!Schema::hasColumn('orders', 'shipping_province')) {
                $after = Schema::hasColumn('orders', 'shipping_province_id')
                    ? 'shipping_province_id'
                    : 'shipping_name';
                $table->string('shipping_province', 100)->nullable()->after($after);
            }
            if (!Schema::hasColumn('orders', 'shipping_city')) {
                $table->string('shipping_city', 100)->nullable()->after('shipping_province');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'shipping_province_id')) {
                $table->dropIndex(['shipping_province_id']);
                $table->dropColumn('shipping_province_id');
            }
            if (Schema::hasColumn('orders', 'shipping_province')) {
                $table->dropColumn('shipping_province');
            }
        });
    }
};
