<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories') || Schema::hasColumn('categories', 'menu_image_id')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('menu_image_id')
                ->nullable()
                ->after('sort')
                ->constrained('image_library')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasColumn('categories', 'menu_image_id')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('menu_image_id');
        });
    }
};
