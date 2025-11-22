<?php

namespace RMS\Shop\Listeners;

use RMS\Shop\Events\OrderPlacedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use RMS\Core\Services\NotificationsService;
use RMS\Core\Models\Setting;

class SendOrderPlacedNotifications implements ShouldQueue
{
    public function handle(OrderPlacedEvent $event): void
    {
        $order = DB::table('orders as o')
            ->leftJoin('users as u','u.id','=','o.user_id')
            ->select('o.*','u.name as user_name','u.telegram_chat_id as user_chat','u.mobile as user_mobile')
            ->where('o.id', (int)$event->orderId)
            ->first();
        if (!$order) return;

        $service = app(NotificationsService::class);

        // Settings: toggles for telegram notifications
        $sendToAdmins = (bool)((int) Setting::get('shop_telegram_order_send_to_admins', 1));
        $sendToChannel = (bool)((int) Setting::get('shop_telegram_order_send_to_channel', 1));

        // Get custom template or use default
        $template = Setting::get('shop_telegram_order_template',
            "<b>سفارش جدید</b>\n\nسفارش #{{ \$order->id }} توسط {{ \$order->user_name ?? ('کاربر #'.\$order->user_id) }} ثبت شد.\n\nمبلغ: <b>{{ number_format((float)\$order->total, 0) }}</b> تومان\n\nوضعیت: <b>{{ \$order->status }}</b>"
        );
        
        // Render the message using Blade syntax
        $message = \Illuminate\Support\Facades\Blade::render($template, ['order' => $order]);

        if ($sendToAdmins) {
            $admins = \RMS\Core\Models\Admin::all();
            foreach ($admins as $admin) {
                $service->sendNow(
                    data: [
                        'notifiable_type' => \RMS\Core\Models\Admin::class,
                        'notifiable_id' => (int)$admin->id,
                        'category' => 'order_new',
                        'title' => 'سفارش جدید',
                        'message' => $message,
                        'meta' => [
                            'order_id' => (int)$order->id,
                            'user_id' => (int)$order->user_id,
                        ],
                    ],
                    channels: [
                        'telegram' => [
                            'chat_id' => config('telegram.bots.mybot.channel_id'),
                            'parse_mode' => 'HTML',
                        ],
                    ]
                );
            }
        }

        if ($sendToChannel) {
            // Also send to main channel once
            app('rms.telegram')
                ->to(config('telegram.bots.mybot.channel_id'))
                ->message($message)
                ->withHtml()
                ->send();
        }
    }
}
