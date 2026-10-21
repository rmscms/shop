<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'max_cart_qty')) {
                // 0 = unlimited (only the storefront/API hard cap applies).
                $table->unsignedInteger('max_cart_qty')->default(0)->after('stock_qty');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'max_cart_qty')) {
                $table->dropColumn('max_cart_qty');
            }
        });
    }
};
