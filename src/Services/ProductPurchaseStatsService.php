<?php

namespace RMS\Shop\Services;

use RMS\Shop\Models\Order;
use RMS\Shop\Models\OrderItem;
use RMS\Shop\Models\ProductPurchaseStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProductPurchaseStatsService
{
    /**
     * Update purchase stats for a specific date
     * 
     * @param Carbon|string|null $date If null, uses yesterday's date
     * @return array Statistics about what was processed
     */
    public function updateForDate($date = null): array
    {
        $targetDate = $date instanceof Carbon 
            ? $date 
            : ($date ? Carbon::parse($date) : Carbon::yesterday());
            
        $targetDateStr = $targetDate->toDateString();
        
        Log::info("ProductPurchaseStats: Processing date {$targetDateStr}");
        
        // Get all orders with paid_at on target date and not refunded
        $orders = Order::query()
            ->whereDate('paid_at', $targetDateStr)
            ->whereNull('refunded_at')
            ->whereNotNull('paid_at')
            ->get(['id', 'user_id', 'paid_at']);
        
        // Load items separately for better performance
        $orderIds = $orders->pluck('id')->all();
        $items = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->get(['id', 'order_id', 'product_id', 'qty', 'total'])
            ->groupBy('order_id');
        
        // Attach items to orders
        $orders = $orders->map(function($order) use ($items) {
            $order->items = $items->get($order->id, collect());
            return $order;
        });
        
        if ($orders->isEmpty()) {
            Log::info("ProductPurchaseStats: No orders found for {$targetDateStr}");
            return [
                'date' => $targetDateStr,
                'orders_processed' => 0,
                'stats_created' => 0,
                'stats_updated' => 0,
            ];
        }
        
        $statsCreated = 0;
        $statsUpdated = 0;
        $processedOrders = 0;
        
        // Group by user_id + product_id
        $grouped = [];
        foreach ($orders as $order) {
            $processedOrders++;
            foreach ($order->items as $item) {
                $key = "{$order->user_id}_{$item->product_id}";
                
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'user_id' => $order->user_id,
                        'product_id' => $item->product_id,
                        'quantity' => 0,
                        'amount' => 0,
                        'order_ids' => [],
                    ];
                }
                
                if ($item->qty > 0) {
                    $grouped[$key]['quantity'] += (int)$item->qty;
                    $grouped[$key]['amount'] += (float)$item->total;
                    if (!in_array((int)$order->id, $grouped[$key]['order_ids'])) {
                        $grouped[$key]['order_ids'][] = (int)$order->id;
                    }
                }
            }
        }
        
        // Upsert stats
        DB::transaction(function() use ($grouped, $targetDateStr, &$statsCreated, &$statsUpdated) {
            foreach ($grouped as $key => $data) {
                $existing = ProductPurchaseStats::query()
                    ->where('user_id', $data['user_id'])
                    ->where('product_id', $data['product_id'])
                    ->where('purchase_date', $targetDateStr)
                    ->first();
                
                if ($existing) {
                    // Update existing
                    $orderIds = array_unique(array_merge(
                        (array)($existing->order_ids ?? []),
                        $data['order_ids']
                    ));
                    
                    $existing->update([
                        'total_quantity' => $existing->total_quantity + $data['quantity'],
                        'total_amount' => $existing->total_amount + $data['amount'],
                        'order_count' => count($orderIds),
                        'order_ids' => $orderIds,
                    ]);
                    $statsUpdated++;
                } else {
                    // Create new
                    ProductPurchaseStats::create([
                        'user_id' => $data['user_id'],
                        'product_id' => $data['product_id'],
                        'purchase_date' => $targetDateStr,
                        'total_quantity' => $data['quantity'],
                        'total_amount' => $data['amount'],
                        'order_count' => count($data['order_ids']),
                        'order_ids' => $data['order_ids'],
                    ]);
                    $statsCreated++;
                }
            }
        });
        
        Log::info("ProductPurchaseStats: Completed {$targetDateStr}", [
            'orders_processed' => $processedOrders,
            'stats_created' => $statsCreated,
            'stats_updated' => $statsUpdated,
        ]);
        
        return [
            'date' => $targetDateStr,
            'orders_processed' => $processedOrders,
            'stats_created' => $statsCreated,
            'stats_updated' => $statsUpdated,
        ];
    }
    
    /**
     * Get purchase stats for a user
     * 
     * @param int $userId
     * @param Carbon|null $since
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserStats(int $userId, ?Carbon $since = null)
    {
        $query = ProductPurchaseStats::query()
            ->where('user_id', $userId)
            ->with('product:id,name,slug')
            ->orderByDesc('purchase_date')
            ->orderByDesc('total_amount');
            
        if ($since) {
            $query->where('purchase_date', '>=', $since);
        }
        
        return $query->get();
    }
    
    /**
     * Get popular products (by purchase count)
     * 
     * @param int|null $limit
     * @param Carbon|null $since
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPopularProducts(?int $limit = null, ?Carbon $since = null)
    {
        return ProductPurchaseStats::getPopularProducts($limit, $since);
    }
}

