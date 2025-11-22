<?php

namespace RMS\Shop\Listeners;

use RMS\Shop\Events\ProductStockDepleted;
use RMS\Shop\Services\CartReservationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class InvalidateCartForDepletedStock implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    private CartReservationService $reservations;

    public function __construct(CartReservationService $reservations)
    {
        $this->reservations = $reservations;
    }

    public function handle(ProductStockDepleted $event): void
    {
        $cartIds = $this->reservations->cartsForProduct($event->productId, $event->combinationId);
        if (empty($cartIds)) {
            return;
        }

        $query = DB::table('cart_items')
            ->whereIn('cart_id', $cartIds)
            ->where('product_id', $event->productId);

        if ($event->combinationId) {
            $query->where('combination_id', $event->combinationId);
        } else {
            $query->whereNull('combination_id');
        }

        $lines = $query->get(['id','cart_id','qty']);
        if ($lines->isEmpty()) {
            return;
        }

        foreach ($lines as $line) {
            $qty = (int) $line->qty;
            DB::table('cart_items')->where('id', (int)$line->id)->delete();
            if ($qty > 0) {
                $this->reservations->syncLine((int)$line->cart_id, $event->productId, $event->combinationId, 0, $qty);
            }
        }
    }
}


