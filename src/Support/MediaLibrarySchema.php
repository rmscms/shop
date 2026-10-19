<?php

namespace RMS\Shop\Support;

use Illuminate\Support\Facades\Schema;

class MediaLibrarySchema
{
    /**
     * Folder tree / CRUD is available when the folders table exists.
     * (Assignment column is checked separately via hasImageFolderColumn.)
     */
    public static function hasImageFolders(): bool
    {
        return Schema::hasTable('image_library_folders');
    }

    /** True when images can be filtered / assigned to folders. */
    public static function hasImageFolderColumn(): bool
    {
        return Schema::hasTable('image_library')
            && Schema::hasColumn('image_library', 'folder_id');
    }

    public static function hasVideoFolders(): bool
    {
        return Schema::hasTable('video_library_folders');
    }

    public static function hasVideoFolderColumn(): bool
    {
        return Schema::hasTable('video_library')
            && Schema::hasColumn('video_library', 'folder_id');
    }

    public static function hasVideoTitle(): bool
    {
        return Schema::hasTable('video_library')
            && Schema::hasColumn('video_library', 'title');
    }
}
