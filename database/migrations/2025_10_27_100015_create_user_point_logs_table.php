<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_point_logs')) {
            Schema::create('user_point_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->integer('change'); // +/-
                $table->string('reason'); // order, refund, manual, correction
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'reason']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_point_logs');
    }
};

