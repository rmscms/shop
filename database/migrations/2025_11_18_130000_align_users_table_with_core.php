<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'group_id')) {
                $table->integer('group_id')->default(4)->after('password');
            }
            if (!Schema::hasColumn('users', 'active')) {
                $table->boolean('active')->default(true)->after('group_id');
            }
            if (!Schema::hasColumn('users', 'email_notifications')) {
                $table->boolean('email_notifications')->default(true)->after('active');
            }
            if (!Schema::hasColumn('users', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('email_notifications');
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('birth_date');
            }
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }

            if (Schema::hasColumn('users', 'group_id')) {
                $table->index('group_id');
            }
            if (Schema::hasColumn('users', 'active')) {
                $table->index('active');
            }
            if (Schema::hasColumn('users', 'email_verified_at')) {
                $table->index('email_verified_at');
            }
            if (Schema::hasColumn('users', 'created_at')) {
                $table->index('created_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'created_at')) {
                $table->dropIndex('users_created_at_index');
            }
            if (Schema::hasColumn('users', 'email_verified_at')) {
                $table->dropIndex('users_email_verified_at_index');
            }
            if (Schema::hasColumn('users', 'active')) {
                $table->dropIndex('users_active_index');
            }
            if (Schema::hasColumn('users', 'group_id')) {
                $table->dropIndex('users_group_id_index');
            }

            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            foreach (['avatar', 'birth_date', 'email_notifications', 'active', 'group_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

