<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('currencies') && !Schema::hasColumn('currencies', 'exchange_rate')) {
            Schema::table('currencies', function (Blueprint $table) {
                $table->decimal('exchange_rate', 12, 6)->default(1)->after('decimals')->comment('نرخ تبدیل به ارز پایه');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('currencies', 'exchange_rate')) {
            Schema::table('currencies', function (Blueprint $table) {
                $table->dropColumn('exchange_rate');
            });
        }
    }
};
