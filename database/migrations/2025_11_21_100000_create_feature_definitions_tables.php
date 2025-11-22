<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_feature_definitions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('product_feature_categories')
                ->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['category_id', 'name'], 'feature_definitions_unique');
        });

        Schema::create('product_feature_definition_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('feature_id')
                ->constrained('product_feature_definitions')
                ->cascadeOnDelete();
            $table->string('value');
            $table->string('slug');
            $table->integer('usage_count')->default(0);
            $table->integer('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['feature_id', 'value'], 'feature_definition_values_unique');
            $table->unique(['feature_id', 'slug'], 'feature_definition_values_slug_unique');
        });

        Schema::table('product_features', function (Blueprint $table) {
            if (!Schema::hasColumn('product_features', 'feature_definition_id')) {
                $table->foreignId('feature_definition_id')
                    ->nullable()
                    ->after('category_id')
                    ->constrained('product_feature_definitions')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('product_features', 'feature_value_id')) {
                $table->foreignId('feature_value_id')
                    ->nullable()
                    ->after('feature_definition_id')
                    ->constrained('product_feature_definition_values')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_features', function (Blueprint $table) {
            if (Schema::hasColumn('product_features', 'feature_value_id')) {
                $table->dropConstrainedForeignId('feature_value_id');
            }
            if (Schema::hasColumn('product_features', 'feature_definition_id')) {
                $table->dropConstrainedForeignId('feature_definition_id');
            }
        });

        Schema::dropIfExists('product_feature_definition_values');
        Schema::dropIfExists('product_feature_definitions');
    }
};

