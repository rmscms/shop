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
        });

        $this->ensureIndex('users', 'group_id');
        $this->ensureIndex('users', 'active');
        $this->ensureIndex('users', 'email_verified_at');
        $this->ensureIndex('users', 'created_at');
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $this->dropIndexIfExists('users', 'created_at');
        $this->dropIndexIfExists('users', 'email_verified_at');
        $this->dropIndexIfExists('users', 'active');
        $this->dropIndexIfExists('users', 'group_id');

        Schema::table('users', function (Blueprint $table) {
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

    private function ensureIndex(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $indexName = "{$table}_{$column}_index";
        if ($this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $indexName) {
            $blueprint->index($column, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $indexName = "{$table}_{$column}_index";
        if (!$this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();

        return match ($connection->getDriverName()) {
            'sqlite' => collect($connection->select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($row) => ($row->name ?? $row->Name ?? null) === $indexName),
            'mysql' => !empty($connection->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName])),
            'pgsql' => !empty($connection->select(
                'SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $indexName]
            )),
            default => false,
        };
    }
};

