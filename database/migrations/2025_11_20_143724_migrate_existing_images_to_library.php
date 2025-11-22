<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create image_assignments table if not exists
        if (!Schema::hasTable('image_assignments')) {
            Schema::create('image_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('image_id')->constrained('image_library')->cascadeOnDelete();
                $table->morphs('assignable');
                $table->boolean('is_main')->default(false);
                $table->integer('sort')->default(0);
                $table->timestamps();
                $table->unique(['image_id', 'assignable_type', 'assignable_id'], 'unique_image_assignment');
                $table->index('is_main');
                $table->index('sort');
            });
        }

        // Step 1: Collect all unique image paths from both tables
        $productImages = DB::table('product_images')->select('path')->distinct()->get();
        $combinationImages = DB::table('product_combination_images')->select('path')->distinct()->get();

        $allPaths = collect([...$productImages, ...$combinationImages])->unique('path');

        // Step 2: Create image_library entries for each unique path
        foreach ($allPaths as $image) {
            $path = $image->path;

            // Extract filename from path
            $filename = basename($path);

            // Get file info if exists
            $sizeBytes = null;
            $mimeType = null;
            if (Storage::disk('public')->exists($path)) {
                $sizeBytes = Storage::disk('public')->size($path);
                $mimeType = Storage::disk('public')->mimeType($path);
            }

            DB::table('image_library')->insert([
                'filename' => $filename,
                'path' => $path, // Keep original path for now
                'size_bytes' => $sizeBytes,
                'mime_type' => $mimeType,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Step 3: Create assignments for product images
        $productImages = DB::table('product_images')->get();
        foreach ($productImages as $img) {
            $imageId = DB::table('image_library')->where('path', $img->path)->value('id');

            if ($imageId) {
                DB::table('image_assignments')->insert([
                    'image_id' => $imageId,
                    'assignable_type' => 'RMS\\Shop\\Models\\Product',
                    'assignable_id' => $img->product_id,
                    'is_main' => $img->is_main ?? false,
                    'sort' => $img->sort ?? 0,
                    'created_at' => $img->created_at ?? now(),
                    'updated_at' => $img->updated_at ?? now(),
                ]);
            }
        }

        // Step 4: Create assignments for combination images
        $combinationImages = DB::table('product_combination_images')->get();
        foreach ($combinationImages as $img) {
            $imageId = DB::table('image_library')->where('path', $img->path)->value('id');

            if ($imageId) {
                DB::table('image_assignments')->insert([
                    'image_id' => $imageId,
                    'assignable_type' => 'RMS\\Shop\\Models\\ProductCombination',
                    'assignable_id' => $img->combination_id,
                    'is_main' => $img->is_main ?? false,
                    'sort' => $img->sort ?? 0,
                    'created_at' => $img->created_at ?? now(),
                    'updated_at' => $img->updated_at ?? now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clean up - remove all migrated data
        DB::table('image_assignments')->truncate();
        DB::table('image_library')->truncate();
    }
};
