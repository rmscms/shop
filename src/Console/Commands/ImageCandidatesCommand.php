<?php

namespace RMS\Shop\Console\Commands;

use Illuminate\Console\Command;
use RMS\Shop\Ai\ImageCandidates;
use RMS\Shop\Models\AiCatalogItem;

class ImageCandidatesCommand extends Command
{
    protected $signature = 'shop:image-candidates
        {file : JSON list of {product_id | item_id | row, images: [{url, source_url, title}]}}
        {--batch= : Catalog batch, needed when entries use "row"}
        {--no-verify : Store links without checking that they return an image}';

    protected $description = 'Add photo suggestions for products or staged catalog rows; admins pick which to download';

    public function handle(ImageCandidates $candidates): int
    {
        $file = (string) $this->argument('file');
        $entries = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($entries)) {
            $this->error("Could not read a JSON list from {$file}.");

            return self::FAILURE;
        }

        $batch = (string) $this->option('batch');
        $totals = ['added' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($entries as $entry) {
            $target = $this->target((array) $entry, $batch);
            if ($target === null) {
                $this->warn('No product or catalog row for '.json_encode(array_diff_key((array) $entry, ['images' => 1])));
                continue;
            }

            $result = $candidates->add($target, (array) ($entry['images'] ?? []), !$this->option('no-verify'));
            $totals['added'] += $result['added'];
            $totals['skipped'] += $result['skipped'];
            $totals['failed'] += count($result['failed']);
            foreach ($result['failed'] as $url) {
                $this->warn('Not an image: '.$url);
            }
        }

        $this->info("Added {$totals['added']} photo suggestions ({$totals['skipped']} already there, {$totals['failed']} rejected).");
        $this->line('Pick them at: '.route('admin.shop.ai.images.index'));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{product_id?: int, catalog_item_id?: int}|null
     */
    protected function target(array $entry, string $batch): ?array
    {
        if (!empty($entry['product_id'])) {
            return ['product_id' => (int) $entry['product_id']];
        }

        $item = null;
        if (!empty($entry['item_id'])) {
            $item = AiCatalogItem::query()->find((int) $entry['item_id']);
        } elseif (isset($entry['row']) && $batch !== '') {
            $item = AiCatalogItem::query()->where('batch', $batch)->where('row', (int) $entry['row'])->first();
        }
        if (!$item) {
            return null;
        }

        // A row that already became a product gives its photos straight to the product.
        return $item->product_id
            ? ['product_id' => $item->product_id, 'catalog_item_id' => $item->id]
            : ['catalog_item_id' => $item->id];
    }
}
