<?php

namespace RMS\Shop\Support;

use Illuminate\Support\Facades\Schema;

class MediaLibrarySchema
{
    public static function hasImageFolders(): bool
    {
        return Schema::hasTable('image_library_folders')
            && Schema::hasTable('image_library')
            && Schema::hasColumn('image_library', 'folder_id');
    }

    public static function hasVideoFolders(): bool
    {
        return Schema::hasTable('video_library_folders')
            && Schema::hasTable('video_library')
            && Schema::hasColumn('video_library', 'folder_id');
    }

    public static function hasVideoTitle(): bool
    {
        return Schema::hasTable('video_library')
            && Schema::hasColumn('video_library', 'title');
    }
}
