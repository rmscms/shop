<?php

namespace RMS\Shop\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RMS\Shop\Ai\ProductAiEnricher;
use RMS\Shop\Models\AiSuggestion;
use Throwable;

class EnrichProductWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public int $suggestionId)
    {
        $this->onQueue((string) config('shop.ai.queue', 'shop-ai'));
    }

    public function handle(ProductAiEnricher $enricher): void
    {
        $suggestion = AiSuggestion::query()->find($this->suggestionId);
        if (!$suggestion || !in_array($suggestion->status, [AiSuggestion::STATUS_PENDING, AiSuggestion::STATUS_PROCESSING], true)) {
            return;
        }

        $enricher->run($suggestion);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Shop AI enrichment failed', ['suggestion' => $this->suggestionId, 'error' => $e->getMessage()]);

        AiSuggestion::query()->whereKey($this->suggestionId)->update([
            'status' => AiSuggestion::STATUS_FAILED,
            'error' => mb_substr($e->getMessage(), 0, 2000),
        ]);
    }
}
