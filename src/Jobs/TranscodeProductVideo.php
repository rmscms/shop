<?php

namespace RMS\Shop\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class TranscodeProductVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $productId;
    public int $videoId;
    public string $sourceRelPath;

    public function __construct(int $productId, int $videoId, string $sourceRelPath)
    {
        $this->productId = $productId;
        $this->videoId = $videoId;
        $this->sourceRelPath = $sourceRelPath;
        $this->onQueue('media');
    }

    public function handle(): void
    {
        $disk = Storage::disk('public');
        $inputPath = $disk->path($this->sourceRelPath);
        if (!is_file($inputPath)) {
            throw new Exception("Source video not found: {$this->sourceRelPath}");
        }

        $baseDirRel = 'uploads/products/'.$this->productId.'/video';
        $hlsDirRel = $baseDirRel.'/hls/'.$this->videoId;
        $posterRel = $baseDirRel.'/poster_'.$this->videoId.'.jpg';
        @mkdir($disk->path($hlsDirRel), 0777, true);

        $variants = [
            ['height' => 480, 'v_bitrate' => '1000k', 'a_bitrate' => '128k'],
            ['height' => 720, 'v_bitrate' => '2500k', 'a_bitrate' => '128k'],
        ];

        $variantDirs = [];
        foreach ($variants as $v) {
            $sub = 'h'.$v['height'];
            $outDir = $disk->path($hlsDirRel.'/'.$sub);
            @mkdir($outDir, 0777, true);
            $playlist = $outDir.DIRECTORY_SEPARATOR.'index.m3u8';
            $segment = $outDir.DIRECTORY_SEPARATOR.'seg_%03d.ts';

            $cmd = [
                $this->bin('ffmpeg'), '-y',
                '-i', $inputPath,
                '-vf', 'scale=-2:'.$v['height'],
                '-c:v', 'h264', '-profile:v', 'main', '-level', '3.1', '-preset', 'veryfast',
                '-b:v', $v['v_bitrate'], '-maxrate', $v['v_bitrate'], '-bufsize', '2M',
                '-c:a', 'aac', '-b:a', $v['a_bitrate'],
                '-f', 'hls', '-hls_time', '4', '-hls_playlist_type', 'vod', '-hls_flags', 'independent_segments',
                '-hls_segment_filename', $segment,
                $playlist,
            ];
            $this->run($cmd, $out, $err);
            $variantDirs[] = ['sub' => $sub, 'height' => $v['height']];
        }

        $masterRel = $hlsDirRel.'/master.m3u8';
        $masterPath = $disk->path($masterRel);
        $bwMap = [480 => 1400000, 720 => 3000000];
        $lines = [
            '#EXTM3U',
            '#EXT-X-VERSION:3',
        ];
        foreach ($variantDirs as $vd) {
            $h = $vd['height'];
            $bw = $bwMap[$h] ?? ($h * 4000);
            $lines[] = "#EXT-X-STREAM-INF:BANDWIDTH={$bw},RESOLUTION=".($h === 480 ? '854x480' : ($h === 720 ? '1280x720' : "1280x{$h}"));
            $lines[] = $vd['sub'].'/index.m3u8';
        }
        @file_put_contents($masterPath, implode("\n", $lines)."\n");

        $this->run([
            $this->bin('ffmpeg'), '-y', '-ss', '1', '-i', $inputPath, '-frames:v', '1', '-q:v', '2', $disk->path($posterRel)
        ], $o1, $e1);

        $width = null;
        $height = null;
        $duration = null;
        $size = null;
        try {
            $probe = $this->runOut([
                $this->bin('ffprobe'), '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=width,height', '-of', 'default=nokey=1:noprint_wrappers=1', $inputPath
            ]);
            $parts = array_values(array_filter(array_map('trim', explode("\n", $probe))));
            if (count($parts) >= 2) { $width = (int)$parts[0]; $height = (int)$parts[1]; }
            $durationS = $this->runOut([
                $this->bin('ffprobe'), '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=nokey=1:noprint_wrappers=1', $inputPath
            ]);
            $duration = (float)trim($durationS);
            $size = is_file($inputPath) ? filesize($inputPath) : null;
        } catch (\Throwable $e) {
        }

        \DB::table('product_videos')->where('id', (int)$this->videoId)->update([
            'source_path' => $this->sourceRelPath,
            'hls_master_path' => $masterRel,
            'poster_path' => $posterRel,
            'size_bytes' => $size,
            'width' => $width,
            'height' => $height,
            'duration_seconds' => $duration,
            'updated_at' => now(),
        ]);
    }

    private function bin(string $name): string
    {
        $key = strtoupper($name).'_BIN';
        $env = env($key);
        return $env ?: $name;
    }

    private function run(array $cmd, ?string &$out, ?string &$err): void
    {
        $process = new Process($cmd);
        $process->setTimeout(null);
        $process->run();
        $out = $process->getOutput();
        $err = $process->getErrorOutput();
        if (!$process->isSuccessful()) {
            Log::warning('FFmpeg process failed', ['cmd' => $cmd, 'err' => $err]);
            throw new Exception('FFmpeg failed');
        }
    }

    private function runOut(array $cmd): string
    {
        $process = new Process($cmd);
        $process->setTimeout(null);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new Exception('Process failed: '.implode(' ', $cmd));
        }
        return $process->getOutput();
    }
}

