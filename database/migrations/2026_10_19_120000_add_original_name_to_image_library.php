<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('image_library') || Schema::hasColumn('image_library', 'original_name')) {
            return;
        }

        Schema::table('image_library', function (Blueprint $table) {
            $table->string('original_name', 255)->nullable()->after('filename');
            $table->index('original_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('image_library') || ! Schema::hasColumn('image_library', 'original_name')) {
            return;
        }

        Schema::table('image_library', function (Blueprint $table) {
            $table->dropIndex(['original_name']);
            $table->dropColumn('original_name');
        });
    }
};
