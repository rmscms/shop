<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('currency_rates')) {
            Schema::create('currency_rates', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('base_code', 10);
                $table->string('quote_code', 10);
                $table->decimal('sell_rate', 18, 6)->nullable();
                $table->dateTime('effective_at');
                $table->string('notes')->nullable();
                $table->timestamps();
                $table->index(['base_code', 'quote_code', 'effective_at'], 'currency_rates_pair_time_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};

