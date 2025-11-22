<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'item_name')) {
                $table->string('item_name')->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('order_items', 'item_attributes')) {
                $table->text('item_attributes')->nullable()->after('item_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'item_attributes')) {
                $table->dropColumn('item_attributes');
            }
            if (Schema::hasColumn('order_items', 'item_name')) {
                $table->dropColumn('item_name');
            }
        });
    }
};

