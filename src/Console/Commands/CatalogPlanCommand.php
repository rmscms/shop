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
        {--sync : Run now instead of queueing}
        {--from-json= : Load a plan written by hand (same shape as the AI answer; items may use "row" instead of "id") instead of calling the AI}';

    protected $description = 'Ask the AI to plan categories, brands and product groups for a staged product list';

    public function handle(AiManager $ai, CatalogPlanner $planner): int
    {
        $batch = (string) $this->argument('batch');
        $staged = AiCatalogItem::query()->where('batch', $batch)->where('status', AiCatalogItem::STATUS_STAGED)->count();
        if ($staged === 0) {
            $this->error("No staged rows in batch {$batch}.");

            return self::FAILURE;
        }

        if ($file = $this->option('from-json')) {
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            if (!is_array($data)) {
                $this->error("Could not read a JSON plan from {$file}.");

                return self::FAILURE;
            }

            return $this->summary($planner->fromData($batch, $data, 'manual: '.basename($file)));
        }

        if (!$ai->configured()) {
            $this->error('AI is not configured. Set SHOP_AI_DRIVER and its API key in .env.');

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

        return $this->summary($plan);
    }

    protected function summary(AiCatalogPlan $plan): int
    {
        $payload = (array) $plan->payload;
        $groups = collect($payload['groups'] ?? []);
        $this->info(sprintf(
            'Plan #%d: %d categories, %d brands, %d groups (%d product pages), %d unplanned rows, %d suspicious rows (tokens in %d / out %d).',
            $plan->id,
            count($payload['categories'] ?? []),
            count($payload['brands'] ?? []),
            $groups->count(),
            $groups->sum(fn ($g) => $g['mode'] === CatalogPlanner::MODE_VARIANTS ? 1 : count($g['items'])),
            $groups->where('unplanned', true)->count(),
            count($payload['suspicious'] ?? []),
            $plan->input_tokens,
            $plan->output_tokens,
        ));
        $this->info('Review and apply it: '.route('admin.shop.ai.catalog.show', $plan));

        return self::SUCCESS;
    }
}
