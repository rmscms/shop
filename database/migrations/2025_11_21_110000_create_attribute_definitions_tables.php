<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_attribute_definitions')) {
            Schema::create('product_attribute_definitions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('type')->default('text');
                $table->string('ui')->default('pill');
                $table->integer('usage_count')->default(0);
                $table->integer('sort')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->unique(['name', 'type', 'ui'], 'attribute_definitions_unique');
            });
        }

        if (!Schema::hasTable('product_attribute_definition_values')) {
            Schema::create('product_attribute_definition_values', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('attribute_definition_id')
                    ->constrained('product_attribute_definitions')
                    ->cascadeOnDelete();
                $table->string('value');
                $table->string('slug');
                $table->string('image_path')->nullable();
                $table->string('color')->nullable();
                $table->integer('usage_count')->default(0);
                $table->integer('sort')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['attribute_definition_id', 'value'], 'attribute_definition_values_unique');
                $table->unique(['attribute_definition_id', 'slug'], 'attribute_definition_values_slug_unique');
            });
        }

        if (!Schema::hasColumn('product_attributes', 'attribute_definition_id')) {
            Schema::table('product_attributes', function (Blueprint $table) {
                $table->foreignId('attribute_definition_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('product_attribute_definitions')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('product_attribute_values', 'definition_value_id')) {
            Schema::table('product_attribute_values', function (Blueprint $table) {
                $table->foreignId('definition_value_id')
                    ->nullable()
                    ->after('attribute_id')
                    ->constrained('product_attribute_definition_values')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('product_attribute_values', function (Blueprint $table) {
            if (Schema::hasColumn('product_attribute_values', 'definition_value_id')) {
                $table->dropConstrainedForeignId('definition_value_id');
            }
        });

        Schema::table('product_attributes', function (Blueprint $table) {
            if (Schema::hasColumn('product_attributes', 'attribute_definition_id')) {
                $table->dropConstrainedForeignId('attribute_definition_id');
            }
        });

        Schema::dropIfExists('product_attribute_definition_values');
        Schema::dropIfExists('product_attribute_definitions');
    }
};

