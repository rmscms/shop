<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'image_id')) {
                $table->unsignedBigInteger('image_id')
                    ->nullable()
                    ->after('combination_id');

                $table->foreign('image_id')
                    ->references('id')
                    ->on('image_library')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('order_items', 'image_snapshot')) {
                $table->json('image_snapshot')
                    ->nullable()
                    ->after('image_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'image_snapshot')) {
                $table->dropColumn('image_snapshot');
            }

            if (Schema::hasColumn('order_items', 'image_id')) {
                $table->dropForeign(['image_id']);
                $table->dropColumn('image_id');
            }
        });
    }
};

