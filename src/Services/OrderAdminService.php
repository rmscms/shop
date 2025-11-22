<?php

namespace RMS\Shop\Services;
use Illuminate\Support\Facades\DB;
use RMS\Shop\Events\OrderStatusUpdated;
use RMS\Shop\Models\Order;

class OrderAdminService
{
    public static function updateStatus(int $orderId, string $status, ?int $actorId = null): void
    {
        $order = DB::table('orders')->where('id',$orderId)->first();
        abort_if(!$order,404);
        $old = (string)$order->status;
        if ($old === $status) { return; }
        DB::table('orders')->where('id',$orderId)->update(['status'=>$status,'updated_at'=>now()]);
        $refundStatuses = (array) config('shop.refund_statuses', ['returned']);
        if (in_array($status, $refundStatuses, true)) {
            $model = Order::find($orderId);
            if ($model) { app(\RMS\Shop\Services\OrderRefundService::class)->refund($model, $actorId ?? 0); }
        }
        event(new OrderStatusUpdated($orderId, $old, $status, $actorId));
    }

    public static function updateTracking(int $orderId, ?string $code, ?string $url): void
    {
        DB::table('orders')->where('id',$orderId)->update([
            'tracking_code' => $code ?: null,
            'tracking_url' => $url ?: null,
            'updated_at' => now(),
        ]);
    }

    public static function applyDiscount(int $orderId, float $amount, ?string $note, int $adminId): void
    {
        $order = DB::table('orders')->where('id',$orderId)->first();
        abort_if(!$order,404);
        if ($amount <= 0) { abort(422, 'Invalid discount amount'); }
        $newDiscount = max(0, (float)$order->discount + $amount);
        $newTotal = max(0, (float)$order->subtotal - $newDiscount + (float)$order->shipping_cost);
        DB::table('orders')->where('id',$orderId)->update([
            'discount' => $newDiscount,
            'total' => $newTotal,
            'updated_at' => now(),
        ]);
        if ($note) {
            DB::table('order_admin_notes')->insert([
                'order_id' => $orderId,
                'admin_id' => $adminId,
                'note_text' => $note,
                'visible_to_user' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public static function applyPoints(int $orderId, int $points): void
    {
        $order = DB::table('orders')->where('id',$orderId)->first();
        abort_if(!$order,404);
        if ($points <= 0) { abort(422, 'Invalid points'); }
        $already = DB::table('user_point_logs')->where(['order_id'=>$orderId,'reason'=>'order'])->exists();
        if ($already) { return; }
        DB::transaction(function() use ($order, $points, $orderId) {
            $row = DB::table('user_points')->where('user_id',(int)$order->user_id)->first();
            if ($row) {
                DB::table('user_points')->where('user_id',(int)$order->user_id)->update([
                    'total_points' => (int)$row->total_points + $points,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('user_points')->insert([
                    'user_id' => (int)$order->user_id,
                    'total_points' => $points,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('user_point_logs')->insert([
                'user_id' => (int)$order->user_id,
                'product_id' => null,
                'order_id' => (int)$orderId,
                'change' => $points,
                'reason' => 'order',
                'meta' => json_encode(['order_id'=>(int)$orderId]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}


