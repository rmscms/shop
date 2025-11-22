<?php

namespace RMS\Shop\Services;

class SystemRequirementsService
{
    /**
     * Check all system requirements for Shop package
     */
    public static function checkAll(): array
    {
        return [
            'php' => self::checkPhpVersion(),
            'extensions' => self::checkExtensions(),
            'directories' => self::checkDirectories(),
            'ffmpeg' => self::checkFfmpeg(),
            'imagemagick' => self::checkImageMagick(),
        ];
    }

    /**
     * Check PHP version
     */
    public static function checkPhpVersion(): array
    {
        $version = PHP_VERSION;
        $required = '8.2.0';
        $status = version_compare($version, $required, '>=');

        return [
            'name' => 'PHP Version',
            'required' => '>= ' . $required,
            'current' => $version,
            'status' => $status,
            'message' => $status 
                ? "PHP $version نصب است" 
                : "PHP $required یا بالاتر نیاز است",
        ];
    }

    /**
     * Check required PHP extensions
     */
    public static function checkExtensions(): array
    {
        $extensions = [
            'gd' => [
                'name' => 'GD Library',
                'description' => 'برای پردازش تصاویر (PNG, JPEG)',
                'required' => true,
            ],
            'exif' => [
                'name' => 'EXIF',
                'description' => 'برای خواندن اطلاعات متادیتای تصاویر',
                'required' => false,
            ],
            'fileinfo' => [
                'name' => 'Fileinfo',
                'description' => 'برای تشخیص نوع فایل',
                'required' => true,
            ],
            'mbstring' => [
                'name' => 'Mbstring',
                'description' => 'برای پشتیبانی از UTF-8',
                'required' => true,
            ],
            'pdo' => [
                'name' => 'PDO',
                'description' => 'برای اتصال به دیتابیس',
                'required' => true,
            ],
        ];

        $results = [];
        foreach ($extensions as $ext => $info) {
            $loaded = extension_loaded($ext);

            $results[$ext] = [
                'name' => $info['name'],
                'description' => $info['description'],
                'required' => $info['required'],
                'status' => $loaded,
                'message' => $loaded ? 'نصب است' : ($info['required'] ? 'نصب نیست (الزامی)' : 'نصب نیست (اختیاری)'),
            ];
        }

        return $results;
    }

    /**
     * Check AVIF support in ImageMagick
     */
    public static function checkAvifSupport(): array
    {
        if (!extension_loaded('imagick') && !class_exists(\Imagick::class, false)) {
            return [
                'status' => true,
                'message' => 'ImageMagick غیرفعال است (اختیاری)',
                'formats' => [],
                'optional' => true,
            ];
        }

        try {
            $imagick = new \Imagick();
            $formats = $imagick->queryFormats();
            $hasAvif = in_array('AVIF', $formats);

            return [
                'status' => $hasAvif,
                'message' => $hasAvif 
                    ? 'پشتیبانی از AVIF فعال است' 
                    : 'ImageMagick بدون پشتیبانی AVIF نصب شده است',
                'formats' => $formats,
                'version' => $imagick->getVersion()['versionString'] ?? 'نامشخص',
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => 'خطا در بررسی ImageMagick: ' . $e->getMessage(),
                'formats' => [],
            ];
        }
    }

    /**
     * Check writable directories
     */
    public static function checkDirectories(): array
    {
        $directories = [
            'storage/app/public' => 'برای ذخیره فایل‌های عمومی',
            'storage/app/public/uploads' => 'برای ذخیره آپلودها',
            'storage/app/public/uploads/products' => 'برای ذخیره تصاویر محصولات',
            'storage/logs' => 'برای ذخیره لاگ‌ها',
        ];

        $results = [];
        foreach ($directories as $dir => $description) {
            $path = base_path($dir);
            $exists = file_exists($path);
            $writable = $exists && is_writable($path);

            $results[$dir] = [
                'path' => $path,
                'description' => $description,
                'exists' => $exists,
                'writable' => $writable,
                'status' => $exists && $writable,
                'message' => !$exists 
                    ? 'پوشه وجود ندارد' 
                    : (!$writable ? 'قابل نوشتن نیست' : 'آماده است'),
            ];
        }

        return $results;
    }

    /**
     * Check FFmpeg for video processing
     */
    public static function checkFfmpeg(): array
    {
        // Get FFmpeg path from config
        $ffmpegPath = config('shop.media.videos.ffmpeg_path', 'ffmpeg');
        $ffprobePath = config('shop.media.videos.ffprobe_path', 'ffprobe');
        
        // Check if ffmpeg command exists
        $output = [];
        $returnVar = 0;
        
        // Try to execute ffmpeg -version
        @exec("$ffmpegPath -version 2>&1", $output, $returnVar);
        
        $exists = $returnVar === 0 && !empty($output);
        $version = $exists ? (preg_match('/ffmpeg version (\S+)/', $output[0], $matches) ? $matches[1] : 'نامشخص') : null;

        // Check ffprobe
        $ffprobeOutput = [];
        $ffprobeReturn = 0;
        @exec("$ffprobePath -version 2>&1", $ffprobeOutput, $ffprobeReturn);
        $ffprobeExists = $ffprobeReturn === 0 && !empty($ffprobeOutput);

        return [
            'name' => 'FFmpeg',
            'description' => 'برای پردازش ویدیوها (تبدیل به HLS)',
            'required' => false,
            'status' => $exists,
            'version' => $version,
            'ffmpeg_path' => $ffmpegPath,
            'ffprobe_path' => $ffprobePath,
            'ffprobe_status' => $ffprobeExists,
            'message' => $exists 
                ? "FFmpeg $version نصب است" . ($ffprobeExists ? ' (با ffprobe)' : ' (بدون ffprobe)')
                : 'FFmpeg نصب نیست (برای پشتیبانی از ویدیو لازم است)',
        ];
    }

    /**
     * Check ImageMagick installation
     */
    public static function checkImageMagick(): array
    {
        $extensionLoaded = extension_loaded('imagick') || class_exists(\Imagick::class, false);
        
        if (!$extensionLoaded) {
            return [
                'name' => 'ImageMagick Extension',
                'description' => 'برای تبدیل تصاویر به فرمت AVIF',
                'required' => false,
                'status' => true,
                'version' => null,
                'avif_support' => false,
                'message' => 'ImageMagick غیرفعال است (اختیاری)',
            ];
        }

        // Check AVIF support
        $avifCheck = self::checkAvifSupport();

        return [
            'name' => 'ImageMagick Extension',
            'description' => 'برای تبدیل تصاویر به فرمت AVIF',
            'required' => true,
            'status' => $extensionLoaded,
            'version' => $avifCheck['version'] ?? null,
            'avif_support' => $avifCheck['status'],
            'message' => $extensionLoaded 
                ? ($avifCheck['status'] 
                    ? 'ImageMagick با پشتیبانی AVIF نصب است' 
                    : 'ImageMagick نصب است اما AVIF پشتیبانی نمی‌شود')
                : 'ImageMagick نصب نیست',
        ];
    }

    /**
     * Get overall system status
     */
    public static function getOverallStatus(): array
    {
        $checks = self::checkAll();
        
        $criticalIssues = [];
        $warnings = [];
        $passed = 0;
        $total = 0;

        // Check PHP version
        $total++;
        if ($checks['php']['status']) {
            $passed++;
        } else {
            $criticalIssues[] = $checks['php']['message'];
        }

        // Check extensions
        foreach ($checks['extensions'] as $ext => $info) {
            $total++;
            if ($info['status']) {
                $passed++;
            } elseif ($info['required']) {
                $criticalIssues[] = "{$info['name']}: {$info['message']}";
            } else {
                $warnings[] = "{$info['name']}: {$info['message']}";
            }
        }

        // Check directories
        foreach ($checks['directories'] as $dir => $info) {
            $total++;
            if ($info['status']) {
                $passed++;
            } else {
                $criticalIssues[] = "$dir: {$info['message']}";
            }
        }

        // Check FFmpeg (warning only)
        $total++;
        if ($checks['ffmpeg']['status']) {
            $passed++;
        } else {
            $warnings[] = $checks['ffmpeg']['message'];
        }

        // Check ImageMagick
        $total++;
        if ($checks['imagemagick']['status']) {
            $passed++;
            if (!$checks['imagemagick']['avif_support']) {
                $warnings[] = 'ImageMagick بدون پشتیبانی AVIF است';
            }
        } else {
            $criticalIssues[] = $checks['imagemagick']['message'];
        }

        $percentage = $total > 0 ? round(($passed / $total) * 100) : 0;
        $ready = empty($criticalIssues);

        return [
            'ready' => $ready,
            'percentage' => $percentage,
            'passed' => $passed,
            'total' => $total,
            'critical_issues' => $criticalIssues,
            'warnings' => $warnings,
            'status_text' => $ready 
                ? 'سیستم آماده است' 
                : 'سیستم نیازمند رفع مشکلات است',
            'status_color' => $ready ? 'success' : (!empty($criticalIssues) ? 'danger' : 'warning'),
        ];
    }
}

