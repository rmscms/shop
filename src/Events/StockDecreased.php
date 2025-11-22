<?php

namespace RMS\Shop\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockDecreased
{
    use Dispatchable, SerializesModels;

    public int $orderId;
    public int $productId;
    public ?int $combinationId;
    public int $quantityDecreased;
    public int $previousStock;
    public int $newStock;
    public string $decreasedAt;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $orderId,
        int $productId,
        ?int $combinationId,
        int $quantityDecreased,
        int $previousStock,
        int $newStock
    ) {
        $this->orderId = $orderId;
        $this->productId = $productId;
        $this->combinationId = $combinationId;
        $this->quantityDecreased = $quantityDecreased;
        $this->previousStock = $previousStock;
        $this->newStock = $newStock;
        $this->decreasedAt = now()->toISOString();
    }
}
