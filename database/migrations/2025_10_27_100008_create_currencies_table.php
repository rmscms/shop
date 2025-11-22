<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 10)->unique();
                $table->string('name');
                $table->string('symbol', 8)->nullable();
                $table->unsignedTinyInteger('decimals')->default(2);
                $table->boolean('is_base')->default(false);
                $table->timestamps();
            });
        }

        if (!DB::table('currencies')->where('code', 'IRT')->exists()) {
            DB::table('currencies')->insert([
                'code' => 'IRT',
                'name' => 'تومان ایران',
                'symbol' => 'تومان',
                'decimals' => 0,
                'is_base' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!DB::table('currencies')->where('code', 'CNY')->exists()) {
            DB::table('currencies')->insert([
                'code' => 'CNY',
                'name' => 'یوان چین',
                'symbol' => '¥',
                'decimals' => 2,
                'is_base' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};

