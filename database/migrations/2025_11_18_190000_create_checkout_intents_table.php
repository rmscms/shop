<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_intents', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('cart_id')->nullable()->index();
            $table->string('cart_key')->nullable();
            $table->string('payment_driver', 100);
            $table->json('cart_snapshot');
            $table->json('address_snapshot');
            $table->json('pricing_snapshot');
            $table->text('customer_note')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_intents');
    }
};

