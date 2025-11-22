<?php

namespace RMS\Shop\Listeners;

use RMS\Shop\Events\OrderStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Blade;
use RMS\Core\Services\NotificationsService;
use RMS\Core\Models\Setting;

class SendOrderStatusUpdatedNotifications implements ShouldQueue
{
    public function handle(OrderStatusUpdated $event): void
    {
        $order = DB::table('orders as o')
            ->leftJoin('users as u','u.id','=','o.user_id')
            ->select('o.*','u.name as user_name','u.telegram_chat_id as user_chat','u.id as uid')
            ->where('o.id', (int)$event->orderId)
            ->first();
        if (!$order) return;

        $service = app(NotificationsService::class);

        // Use custom template from settings if provided; fallback to view
        $tpl = (string) Setting::get('shop_telegram_order_update_template', '');
        if ($tpl) {
            $message = Blade::render($tpl, [
                'order' => $order,
                'old' => $event->oldStatus,
                'new' => $event->newStatus,
            ]);
        } else {
            $message = view('notifications.shop.order-status-updated', [
                'order' => $order,
                'old' => $event->oldStatus,
                'new' => $event->newStatus,
            ])->render();
        }

        $sendToUser = (bool) ((int) Setting::get('shop_telegram_status_send_to_user', 1));

        $channels = [];
        if ($sendToUser) {
            $channels['telegram'] = [
                'chat_id' => $order->user_chat ?: config('telegram.bots.mybot.channel_id'),
                'parse_mode' => 'HTML',
            ];
        }

        $service->sendNow(
            data: [
                'notifiable_type' => \App\Models\User::class,
                'notifiable_id' => (int)$order->uid,
                'category' => 'order_status',
                'title' => 'به‌روزرسانی وضعیت سفارش',
                'message' => $message,
                'meta' => [
                    'order_id' => (int)$order->id,
                    'old_status' => $event->oldStatus,
                    'new_status' => $event->newStatus,
                ],
            ],
            channels: $channels
        );
    }
}
