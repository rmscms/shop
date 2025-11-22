<?php

namespace RMS\Shop\Listeners;

use RMS\Shop\Events\OrderPlacedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use RMS\Shop\Models\Product;

class UpdateProductAvailabilityCache implements ShouldQueue
{
    public function handle(OrderPlacedEvent $event): void
    {
        $productIds = DB::table('order_items')
            ->where('order_id', (int)$event->orderId)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();
        foreach ($productIds as $pid) {
            Product::refreshAvailabilityCache((int)$pid);
        }
    }
}
