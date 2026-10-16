<?php

namespace RMS\Shop\Console\Commands;

use Illuminate\Console\Command;
use RMS\Shop\Ai\AiManager;
use RMS\Shop\Ai\ProductAiEnricher;
use RMS\Shop\Jobs\EnrichProductWithAi;
use RMS\Shop\Models\AiSuggestion;
use RMS\Shop\Models\Product;
use Throwable;

class AiEnrichProductsCommand extends Command
{
    protected $signature = 'shop:ai-enrich
        {--product=* : Only these product ids}
        {--limit=0 : Stop after this many products}
        {--missing : Only products without a description}
        {--force : Also redo products that already have an open or applied suggestion}
        {--sync : Run now instead of queueing (good for testing one product)}
        {--from-json= : Load suggestions written by hand: [{product_id, name, short_desc, description, feature_groups, ...}] instead of calling the AI}';

    protected $description = 'Ask the AI to suggest Persian copy, category, brand and features for products';

    public function handle(AiManager $ai, ProductAiEnricher $enricher): int
    {
        if ($file = $this->option('from-json')) {
            return $this->fromJson((string) $file, $enricher);
        }

        if (!$ai->configured()) {
            $this->error('AI is not configured. Set SHOP_AI_DRIVER and its API key in .env.');

            return self::FAILURE;
        }

        $query = Product::query()->orderBy('id');

        if ($ids = array_filter(array_map('intval', (array) $this->option('product')))) {
            $query->whereIn('id', $ids);
        }
        if ($this->option('missing')) {
            $query->where(fn ($q) => $q->whereNull('description')->orWhere('description', ''));
        }
        if (!$this->option('force')) {
            $query->whereNotIn('id', AiSuggestion::query()
                ->whereIn('status', [AiSuggestion::STATUS_PENDING, AiSuggestion::STATUS_PROCESSING, AiSuggestion::STATUS_READY, AiSuggestion::STATUS_APPLIED])
                ->select('product_id'));
        }
        if (($limit = (int) $this->option('limit')) > 0) {
            $query->limit($limit);
        }

        $products = $query->get();
        if ($products->isEmpty()) {
            $this->info('No products to process.');

            return self::SUCCESS;
        }

        $sync = (bool) $this->option('sync');
        $this->info(($sync ? 'Processing ' : 'Queueing ').$products->count().' product(s) with '.$ai->driverName().'.');
        $bar = $this->output->createProgressBar($products->count());
        $failed = 0;

        foreach ($products->values() as $index => $product) {
            $suggestion = $enricher->queue($product);

            if ($sync) {
                try {
                    $enricher->run($suggestion);
                } catch (Throwable $e) {
                    $failed++;
                    $suggestion->forceFill(['status' => AiSuggestion::STATUS_FAILED, 'error' => $e->getMessage()])->save();
                    $this->newLine();
                    $this->warn("#{$product->id}: ".$e->getMessage());
                }
            } else {
                EnrichProductWithAi::dispatch($suggestion->id)->delay(now()->addSeconds($ai->dispatchDelay($index)));
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (!$sync) {
            $this->line('Run a worker for the queue: php artisan queue:work --queue='.config('shop.ai.queue', 'shop-ai'));
        }
        $this->info('Review the results in the admin panel: '.route('admin.shop.ai.index'));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Each entry is one suggestion in the AI answer's shape plus the product_id it belongs to.
     */
    protected function fromJson(string $file, ProductAiEnricher $enricher): int
    {
        $entries = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($entries)) {
            $this->error("Could not read a JSON list from {$file}.");

            return self::FAILURE;
        }

        $created = 0;
        foreach ($entries as $entry) {
            $product = Product::query()->find((int) ($entry['product_id'] ?? 0));
            if (!$product) {
                $this->warn('Unknown product_id '.($entry['product_id'] ?? '(none)'));
                continue;
            }
            unset($entry['product_id']);
            $enricher->fromData($product, (array) $entry, 'manual: '.basename($file));
            $created++;
        }

        $this->info("{$created} suggestion(s) ready for review: ".route('admin.shop.ai.index', ['status' => 'ready']));

        return self::SUCCESS;
    }
}
