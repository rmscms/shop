<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status')->default('pending');
                $table->decimal('subtotal', 18, 2)->default(0);
                $table->decimal('discount', 18, 2)->default(0);
                $table->decimal('shipping_cost', 18, 2)->default(0);
                $table->decimal('total', 18, 2)->default(0);
                $table->dateTime('paid_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

