<?php

namespace RMS\Shop\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductStockDepleted
{
    use Dispatchable, SerializesModels;

    public int $productId;
    public ?int $combinationId;

    public function __construct(int $productId, ?int $combinationId = null)
    {
        $this->productId = $productId;
        $this->combinationId = $combinationId;
    }
}


