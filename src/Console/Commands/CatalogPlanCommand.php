<?php

namespace RMS\Shop\Console\Commands;

use Illuminate\Console\Command;
use RMS\Shop\Ai\AiManager;
use RMS\Shop\Ai\CatalogPlanner;
use RMS\Shop\Jobs\PlanCatalogWithAi;
use RMS\Shop\Models\AiCatalogItem;
use RMS\Shop\Models\AiCatalogPlan;
use Throwable;

class CatalogPlanCommand extends Command
{
    protected $signature = 'shop:catalog-plan
        {batch : Batch name given by shop:catalog-import}
        {--sync : Run now instead of queueing}';

    protected $description = 'Ask the AI to plan categories, brands and product groups for a staged product list';

    public function handle(AiManager $ai, CatalogPlanner $planner): int
    {
        if (!$ai->configured()) {
            $this->error('AI is not configured. Set SHOP_AI_DRIVER and its API key in .env.');

            return self::FAILURE;
        }

        $batch = (string) $this->argument('batch');
        $staged = AiCatalogItem::query()->where('batch', $batch)->where('status', AiCatalogItem::STATUS_STAGED)->count();
        if ($staged === 0) {
            $this->error("No staged rows in batch {$batch}.");

            return self::FAILURE;
        }

        $plan = $planner->queue($batch);

        if (!$this->option('sync')) {
            PlanCatalogWithAi::dispatch($plan->id);
            $this->info("Plan #{$plan->id} queued for {$staged} rows. Worker: php artisan queue:work --queue=".config('shop.ai.queue', 'shop-ai'));

            return self::SUCCESS;
        }

        $this->info("Planning {$staged} rows with ".$ai->driverName().'...');
        try {
            $planner->run($plan);
        } catch (Throwable $e) {
            $plan->forceFill(['status' => AiCatalogPlan::STATUS_FAILED, 'error' => $e->getMessage()])->save();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $payload = (array) $plan->payload;
        $this->info(sprintf(
            'Plan #%d: %d categories, %d brands, %d groups, %d suspicious rows (tokens in %d / out %d).',
            $plan->id,
            count($payload['categories'] ?? []),
            count($payload['brands'] ?? []),
            count($payload['groups'] ?? []),
            count($payload['suspicious'] ?? []),
            $plan->input_tokens,
            $plan->output_tokens,
        ));
        $this->info('Review and apply it: '.route('admin.shop.ai.catalog.show', $plan));

        return self::SUCCESS;
    }
}
