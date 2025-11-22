<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('order_admin_notes', 'body') && !Schema::hasColumn('order_admin_notes', 'note_text')) {
            Schema::table('order_admin_notes', function (Blueprint $table) {
                $table->renameColumn('body', 'note_text');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_admin_notes', 'note_text') && !Schema::hasColumn('order_admin_notes', 'body')) {
            Schema::table('order_admin_notes', function (Blueprint $table) {
                $table->renameColumn('note_text', 'body');
            });
        }
    }
};

