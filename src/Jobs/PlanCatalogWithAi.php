<?php

namespace RMS\Shop\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RMS\Shop\Ai\CatalogPlanner;
use RMS\Shop\Models\AiCatalogPlan;
use Throwable;

class PlanCatalogWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 360;

    /** @var array<int, int> */
    public array $backoff = [60];

    public function __construct(public int $planId)
    {
        $this->onQueue((string) config('shop.ai.queue', 'shop-ai'));
    }

    public function handle(CatalogPlanner $planner): void
    {
        $plan = AiCatalogPlan::query()->find($this->planId);
        if (!$plan || !in_array($plan->status, [AiCatalogPlan::STATUS_PENDING, AiCatalogPlan::STATUS_PROCESSING], true)) {
            return;
        }

        $planner->run($plan);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Shop AI catalog plan failed', ['plan' => $this->planId, 'error' => $e->getMessage()]);

        AiCatalogPlan::query()->whereKey($this->planId)->update([
            'status' => AiCatalogPlan::STATUS_FAILED,
            'error' => mb_substr($e->getMessage(), 0, 2000),
        ]);
    }
}
