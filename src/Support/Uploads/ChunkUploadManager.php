<?php

namespace RMS\Shop\Support\Uploads;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ChunkUploadManager
{
    protected string $metaDir;

    public function __construct()
    {
        $this->metaDir = storage_path('app/tmp/chunks');
        if (!is_dir($this->metaDir)) {
            @mkdir($this->metaDir, 0777, true);
        }
    }

    public function init(array $meta): string
    {
        $uploadId = (string) Str::uuid();
        $payload = [
            'filename' => (string) ($meta['filename'] ?? $meta['original_name'] ?? 'file.bin'),
            'mime' => $meta['mime'] ?? null,
            'chunk_size' => (int) ($meta['chunk_size'] ?? 0),
            'total_size' => (int) ($meta['total_size'] ?? 0),
            'created_at' => now()->toIso8601String(),
        ];

        $this->writeMeta($uploadId, $payload);

        return $uploadId;
    }

    /**
     * @return array{done:bool,path?:string,url?:string,filename?:string,received?:int}
     */
    public function append(string $uploadId, UploadedFile $chunk, int $index, int $count, string $filename): array
    {
        $this->assertMetaExists($uploadId);

        $tmpFile = $this->metaDir.DIRECTORY_SEPARATOR.$uploadId.'.part';
        $mode = $index === 0 ? 'wb' : 'ab';
        $input = fopen($chunk->getRealPath(), 'rb');
        $output = fopen($tmpFile, $mode);
        stream_copy_to_stream($input, $output);
        fclose($input);
        fclose($output);

        $done = ($index + 1) >= $count;
        if (!$done) {
            return [
                'done' => false,
                'received' => $index,
            ];
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'bin');
        $destDir = 'uploads/tmp/'.$uploadId.'/orig';
        $destName = Str::uuid().'.'.$ext;
        Storage::disk('public')->makeDirectory($destDir);
        $destPath = $destDir.'/'.$destName;
        $destAbs = Storage::disk('public')->path($destPath);
        @rename($tmpFile, $destAbs);

        $this->cleanup($uploadId);

        return [
            'done' => true,
            'path' => $destPath,
            'url' => Storage::disk('public')->url($destPath),
            'filename' => $filename,
        ];
    }

    protected function writeMeta(string $uploadId, array $payload): void
    {
        $file = $this->metaDir.DIRECTORY_SEPARATOR.$uploadId.'.json';
        file_put_contents($file, json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    protected function assertMetaExists(string $uploadId): void
    {
        $file = $this->metaDir.DIRECTORY_SEPARATOR.$uploadId.'.json';
        if (!is_file($file)) {
            throw new RuntimeException('upload_session_not_found');
        }
    }

    protected function cleanup(string $uploadId): void
    {
        @unlink($this->metaDir.DIRECTORY_SEPARATOR.$uploadId.'.json');
    }
}

