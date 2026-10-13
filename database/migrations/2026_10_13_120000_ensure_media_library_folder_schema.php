<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('image_library_folders')) {
            Schema::create('image_library_folders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')
                    ->nullable()
                    ->constrained('image_library_folders')
                    ->nullOnDelete();
                $table->string('name');
                $table->string('slug', 190);
                $table->string('path', 500);
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();

                $table->unique(['parent_id', 'slug']);
                $table->index('path');
            });
        }

        if (Schema::hasTable('image_library') && ! Schema::hasColumn('image_library', 'folder_id')) {
            Schema::table('image_library', function (Blueprint $table) {
                $table->foreignId('folder_id')
                    ->nullable()
                    ->after('mime_type')
                    ->constrained('image_library_folders')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('video_library_folders')) {
            Schema::create('video_library_folders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')
                    ->nullable()
                    ->constrained('video_library_folders')
                    ->nullOnDelete();
                $table->string('name');
                $table->string('slug', 190);
                $table->string('path', 500);
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();

                $table->unique(['parent_id', 'slug']);
                $table->index('path');
            });
        }

        if (Schema::hasTable('video_library') && ! Schema::hasColumn('video_library', 'folder_id')) {
            Schema::table('video_library', function (Blueprint $table) {
                $table->foreignId('folder_id')
                    ->nullable()
                    ->after('size_bytes')
                    ->constrained('video_library_folders')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('video_library') && ! Schema::hasColumn('video_library', 'title')) {
            Schema::table('video_library', function (Blueprint $table) {
                $table->string('title')->nullable()->after('filename');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('image_library') && Schema::hasColumn('image_library', 'folder_id')) {
            Schema::table('image_library', function (Blueprint $table) {
                $table->dropConstrainedForeignId('folder_id');
            });
        }

        if (Schema::hasTable('video_library') && Schema::hasColumn('video_library', 'folder_id')) {
            Schema::table('video_library', function (Blueprint $table) {
                $table->dropConstrainedForeignId('folder_id');
            });
        }

        Schema::dropIfExists('image_library_folders');
        Schema::dropIfExists('video_library_folders');
    }
};
