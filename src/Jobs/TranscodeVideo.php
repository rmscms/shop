<?php

namespace RMS\Shop\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RMS\Shop\Models\VideoLibrary;
use Symfony\Component\Process\Process;
use Illuminate\Support\Str;

class TranscodeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $videoId;

    public $tries = 3;
    public $timeout = 3600; // 1 hour

    public function __construct(int $videoId)
    {
        $this->videoId = $videoId;
        $this->onQueue('videos');
    }

    public function handle(): void
    {
        $video = VideoLibrary::findOrFail($this->videoId);
        
        // Normalize path separators for Windows
        $originalPath = str_replace('/', DIRECTORY_SEPARATOR, Storage::disk('public')->path($video->path));
        
        $baseName = Str::random(40);
        $hlsDir = "uploads/products/videos/hls/{$baseName}";
        $hlsPath = "{$hlsDir}/master.m3u8";
        $posterPath = "uploads/products/videos/posters/{$baseName}.jpg";

        // Create directories
        Storage::disk('public')->makeDirectory($hlsDir);
        Storage::disk('public')->makeDirectory('uploads/products/videos/posters');

        // Check if FFmpeg exists
        $ffmpeg = env('FFMPEG_BINARIES', 'ffmpeg');
        
        // Try to transcode with FFmpeg
        try {
            // Extract poster first
            $posterFullPath = str_replace('/', DIRECTORY_SEPARATOR, Storage::disk('public')->path($posterPath));
            $posterProcess = new Process([
                $ffmpeg,
                '-i', $originalPath,
                '-ss', '00:00:01.000',
                '-vframes', '1',
                $posterFullPath
            ]);
            $posterProcess->run();
            
            if (!$posterProcess->isSuccessful()) {
                \Log::warning('Poster extraction failed', [
                    'video_id' => $video->id,
                    'error' => $posterProcess->getErrorOutput(),
                    'command' => $posterProcess->getCommandLine(),
                ]);
            }

            // Transcode to HLS
            $hlsFullPath = str_replace('/', DIRECTORY_SEPARATOR, Storage::disk('public')->path($hlsPath));
            $segmentPattern = str_replace('/', DIRECTORY_SEPARATOR, Storage::disk('public')->path("{$hlsDir}/segment_%03d.ts"));
            
            $process = new Process([
                $ffmpeg,
                '-i', $originalPath,
                '-vf', 'scale=w=1280:h=720:force_original_aspect_ratio=decrease,pad=1280:720:-1:-1:color=black',
                '-c:a', 'aac',
                '-ar', '48000',
                '-c:v', 'h264',
                '-profile:v', 'main',
                '-crf', '20',
                '-g', '48',
                '-keyint_min', '48',
                '-sc_threshold', '0',
                '-b:v', '2500k',
                '-maxrate', '2675k',
                '-bufsize', '3750k',
                '-b:a', '128k',
                '-hls_time', '4',
                '-hls_playlist_type', 'vod',
                '-hls_segment_filename', $segmentPattern,
                $hlsFullPath
            ]);
            
            $process->setTimeout(3600); // 1 hour timeout
            $process->run();
            
            if (!$process->isSuccessful()) {
                \Log::error('HLS transcode failed', [
                    'video_id' => $video->id,
                    'error' => $process->getErrorOutput(),
                    'command' => $process->getCommandLine(),
                ]);
                throw new \Exception('FFmpeg failed: ' . $process->getErrorOutput());
            }

            // Get duration
            $duration = $this->getVideoDuration($originalPath);

            // Update video
            $video->update([
                'hls_path' => $hlsPath,
                'poster_path' => $posterPath,
                'duration_seconds' => $duration,
            ]);
        } catch (\Exception $e) {
            \Log::error('Video transcoding failed: ' . $e->getMessage());
            // Video remains without HLS, but still accessible via original path
        }
    }

    protected function getVideoDuration(string $path): ?int
    {
        try {
            $ffprobe = env('FFPROBE_BINARIES', 'ffprobe');
            $process = new Process([
                $ffprobe,
                '-v', 'error',
                '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1',
                $path
            ]);
            $process->run();

            return (int) $process->getOutput();
        } catch (\Exception $e) {
            return null;
        }
    }
}

