<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_feature_categories')) {
            Schema::create('product_feature_categories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('icon')->nullable();
                $table->text('description')->nullable();
                $table->integer('sort')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('product_features')) {
            Schema::create('product_features', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('product_feature_categories')->cascadeOnDelete();
                $table->string('name');
                $table->string('value');
                $table->integer('sort')->default(0);
                $table->timestamps();
                $table->unique(['product_id', 'category_id', 'name'], 'product_features_unique');
            });
        }

        if (!Schema::hasTable('product_ratings')) {
            Schema::create('product_ratings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->string('title')->nullable();
                $table->text('body')->nullable();
                $table->timestamps();
                $table->unique(['product_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('product_purchase_stats')) {
            Schema::create('product_purchase_stats', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->date('purchase_date');
                $table->integer('total_quantity')->default(0);
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->integer('order_count')->default(0);
                $table->json('order_ids')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'product_id', 'purchase_date'], 'product_purchase_stats_unique');
                $table->index('purchase_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_purchase_stats');
        Schema::dropIfExists('product_ratings');
        Schema::dropIfExists('product_features');
        Schema::dropIfExists('product_feature_categories');
    }
};

