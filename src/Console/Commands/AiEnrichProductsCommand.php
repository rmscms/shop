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
        {--sync : Run now instead of queueing (good for testing one product)}';

    protected $description = 'Ask the AI to suggest Persian copy, category, brand and features for products';

    public function handle(AiManager $ai, ProductAiEnricher $enricher): int
    {
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
}
