<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_ai_activities')) {
            return;
        }

        Schema::create('shop_ai_activities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('access_id')->nullable();
            $table->string('actor', 190)->nullable();
            // review, suggestion, apply, write, note
            $table->string('type', 20)->index();
            $table->string('title', 255);
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_ai_activities');
    }
};
