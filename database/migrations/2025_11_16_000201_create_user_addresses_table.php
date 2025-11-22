<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_addresses')) {
            Schema::create('user_addresses', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('full_name');
                $table->string('mobile', 50)->nullable();
                $table->string('phone', 50)->nullable();
                $table->unsignedTinyInteger('province_id')->nullable();
                $table->string('province')->nullable();
                $table->string('city')->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->text('address_line');
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index('province_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};

