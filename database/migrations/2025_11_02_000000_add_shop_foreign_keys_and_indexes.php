<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Index helpers - Safe: Try to add index, ignore if already exists (duplicate key)
        // We use try-catch instead of hasIndex() check because index names might differ or detection might fail
        try {
            Schema::table('product_images', function (Blueprint $table) {
                $table->index('product_id');
            });
        } catch (\Throwable $e) {
            // Index might already exist, ignore duplicate key error
        }
        
        try {
            Schema::table('product_attributes', function (Blueprint $table) {
                $table->index('product_id');
            });
        } catch (\Throwable $e) {
            // Index might already exist, ignore
        }
        
        try {
            Schema::table('product_attribute_values', function (Blueprint $table) {
                $table->index('attribute_id', 'pav_attribute_id_index');
            });
        } catch (\Throwable $e) {
            // Index might already exist, ignore
        }
        
        try {
            Schema::table('product_combinations', function (Blueprint $table) {
                $table->index('product_id');
                $table->index('active');
            });
        } catch (\Throwable $e) {
            // Index might already exist, ignore
        }
        
        try {
            Schema::table('product_combination_values', function (Blueprint $table) {
                $table->index('combination_id', 'pcv_combination_id_index');
                $table->index('attribute_value_id', 'pcv_attribute_value_id_index');
            });
        } catch (\Throwable $e) {
            // Index might already exist, ignore
        }
        
        try {
            Schema::table('product_combination_images', function (Blueprint $table) {
                $table->index('combination_id', 'pci_combination_id_index');
            });
        } catch (\Throwable $e) {
            // Index might already exist, ignore
        }
        
        // NOTE: product_videos.product_id index already exists from migration 2025_11_01_120000_update_product_videos_to_multiple.php
        // Skip adding it here to avoid duplicate key error

        // NOTE: Per requirement, we do NOT add foreign keys to avoid accidental cascades.
    }

    public function down(): void
    {
        // Drop indexes (optional)
        Schema::table('product_images', function (Blueprint $table) { if (self::hasIndex('product_images', 'product_images_product_id_index')) $table->dropIndex('product_images_product_id_index'); });
        Schema::table('product_attributes', function (Blueprint $table) { if (self::hasIndex('product_attributes', 'product_attributes_product_id_index')) $table->dropIndex('product_attributes_product_id_index'); });
        Schema::table('product_attribute_values', function (Blueprint $table) { if (self::hasIndex('product_attribute_values', 'pav_attribute_id_index')) $table->dropIndex('pav_attribute_id_index'); });
        Schema::table('product_combinations', function (Blueprint $table) {
            if (self::hasIndex('product_combinations', 'product_combinations_product_id_index')) $table->dropIndex('product_combinations_product_id_index');
            if (self::hasIndex('product_combinations', 'product_combinations_active_index')) $table->dropIndex('product_combinations_active_index');
        });
        Schema::table('product_combination_values', function (Blueprint $table) {
            if (self::hasIndex('product_combination_values', 'pcv_combination_id_index')) $table->dropIndex('pcv_combination_id_index');
            if (self::hasIndex('product_combination_values', 'pcv_attribute_value_id_index')) $table->dropIndex('pcv_attribute_value_id_index');
        });
        Schema::table('product_combination_images', function (Blueprint $table) { if (self::hasIndex('product_combination_images', 'pci_combination_id_index')) $table->dropIndex('pci_combination_id_index'); });
        // NOTE: product_videos.product_id index is managed by migration 2025_11_01_120000_update_product_videos_to_multiple.php
        // Don't drop it here
    }

    private static function hasForeign(string $table, string $name): bool
    {
        try {
            $connection = Schema::getConnection();
            $schemaManager = $connection->getDoctrineSchemaManager();
            $doctrineSchema = $schemaManager->listTableDetails($table);
            return $doctrineSchema->hasForeignKey($name);
        } catch (\Throwable $e) { return false; }
    }

    private static function hasIndex(string $table, string $name): bool
    {
        try {
            $connection = Schema::getConnection();
            $schemaManager = $connection->getDoctrineSchemaManager();
            $doctrineSchema = $schemaManager->listTableDetails($table);
            return $doctrineSchema->hasIndex($name);
        } catch (\Throwable $e) { return false; }
    }
};


