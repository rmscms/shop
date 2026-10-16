<?php

namespace RMS\Shop\Ai;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RMS\Shop\Models\AiImageCandidate;
use RMS\Shop\Models\Product;
use RMS\Shop\Services\ImageLibraryService;
use RuntimeException;

/**
 * Photo suggestions for products. Candidates are only links until an admin
 * picks one; then it is downloaded into the image library and assigned.
 */
class ImageCandidates
{
    protected const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    protected const MAX_BYTES = 5 * 1024 * 1024;

    protected const MIN_SIDE = 200;

    /**
     * @param  array{product_id?: int|null, catalog_item_id?: int|null}  $target
     * @param  array<int, array{url: string, source_url?: string|null, title?: string|null}>  $images
     * @return array{added: int, skipped: int, failed: array<int, string>}
     */
    public function add(array $target, array $images, bool $verify = true): array
    {
        $target = array_filter([
            'product_id' => $target['product_id'] ?? null,
            'catalog_item_id' => $target['catalog_item_id'] ?? null,
        ]);
        $added = $skipped = 0;
        $failed = [];

        foreach ($images as $image) {
            $url = trim((string) ($image['url'] ?? ''));
            if (!preg_match('#^https?://#i', $url)) {
                $failed[] = $url;
                continue;
            }
            if (AiImageCandidate::query()->where($target)->where('url', $url)->exists()) {
                $skipped++;
                continue;
            }
            if ($verify && !$this->isImage($url)) {
                $failed[] = $url;
                continue;
            }

            $source = trim((string) ($image['source_url'] ?? ''));
            AiImageCandidate::query()->create($target + [
                'url' => mb_substr($url, 0, 1000),
                'source_url' => preg_match('#^https?://#i', $source) ? mb_substr($source, 0, 1000) : null,
                'title' => mb_substr(trim((string) ($image['title'] ?? '')), 0, 300) ?: null,
                'status' => AiImageCandidate::STATUS_CANDIDATE,
            ]);
            $added++;
        }

        return ['added' => $added, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * A usable product photo: reachable, JPEG/PNG/WebP, not huge, and not a tiny placeholder.
     */
    public function isImage(string $url): bool
    {
        try {
            $response = $this->http()->get($url);
        } catch (\Throwable) {
            return false;
        }

        $body = (string) $response->body();
        $size = @getimagesizefromstring($body);

        return $response->successful()
            && isset(self::MIME_EXTENSIONS[$this->mime((string) $response->header('Content-Type'))])
            && strlen($body) <= self::MAX_BYTES
            && $size !== false
            && $size[0] >= self::MIN_SIDE
            && $size[1] >= self::MIN_SIDE;
    }

    /**
     * Download the photo, add it to the image library and assign it to the product.
     * The first photo of a product without images becomes its main image.
     */
    public function attach(AiImageCandidate $candidate): AiImageCandidate
    {
        $product = Product::query()->find($candidate->product_id);
        if (!$product) {
            throw new RuntimeException('محصول این عکس هنوز ساخته نشده است.');
        }

        $response = $this->http()->get($candidate->url);
        $mime = $this->mime((string) $response->header('Content-Type'));
        $body = (string) $response->body();

        if (!$response->successful() || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new RuntimeException('لینک عکس جواب نداد یا عکس نبود ('.$response->status().', '.$mime.').');
        }
        if (strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException('عکس بزرگ‌تر از ۵ مگابایت است.');
        }
        $size = @getimagesizefromstring($body);
        if ($size === false || $size[0] < self::MIN_SIDE || $size[1] < self::MIN_SIDE) {
            throw new RuntimeException('فایل دانلودشده عکس معتبر نیست یا خیلی کوچک است.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'shop-ai-img');
        file_put_contents($tmp, $body);

        try {
            $file = new UploadedFile($tmp, 'ai-'.$candidate->id.'.'.self::MIME_EXTENSIONS[$mime], $mime, null, true);
            $image = ImageLibraryService::uploadImage($file);

            $count = $product->assignedImages()->count();
            ImageLibraryService::assignImage($image, $product, $count === 0, $count);
        } finally {
            @unlink($tmp);
        }

        $candidate->forceFill([
            'status' => AiImageCandidate::STATUS_ATTACHED,
            'image_id' => $image->id,
            'error' => null,
        ])->save();

        return $candidate;
    }

    protected function http()
    {
        return Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                'Accept' => 'image/webp,image/png,image/jpeg,*/*;q=0.8',
            ])
            ->withOptions(['allow_redirects' => ['max' => 5]]);
    }

    protected function mime(string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType)[0]));
    }
}
