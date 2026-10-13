<?php

namespace RMS\Shop\Http\Controllers\Admin;

use RMS\Shop\Http\Controllers\Admin\ShopAdminController;
use RMS\Core\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class ShopSettingsController extends ShopAdminController
{
    public function table(): string { return 'settings'; }
    public function modelName(): string { return Setting::class; }

    public function edit(Request $request, string|int $id = 0)
    {
        $this->title('تنظیمات شاپ');

        // Basic settings
        $purchaseEnabled = (bool) ((int) Setting::get('shop_purchase_enabled', 1));
        $shippingFlat = (float) (Setting::get('shop_shipping_flat', 0) ?? 0);
        $enablePricingStock = (bool) ((int) Setting::get('shop_enable_pricing_stock_management', 1));

        // Telegram templates and toggles
        $telegramOrderTemplate = Setting::get('shop_telegram_order_template',
            "<b>سفارش جدید</b>\n\nسفارش #{{ \$order->id }} توسط {{ \$order->user_name ?? ('کاربر #'.\$order->user_id) }} ثبت شد.\n\nمبلغ: <b>{{ number_format((float)\$order->total, 0) }}</b> تومان\n\nوضعیت: <b>{{ \$order->status }}</b>"
        );
        $telegramOrderUpdateTemplate = Setting::get('shop_telegram_order_update_template',
            "<b>بروزرسانی سفارش</b>\n\nسفارش #{{ \$order->id }}\n\nوضعیت: <b>{{ \$new }}</b>\n\n@if(!empty(\$order->tracking_code))\nکد رهگیری: <code>{{ \$order->tracking_code }}</code>\n@endif\n@if(!empty(\$order->tracking_url))\n<a href=\"{{ \$order->tracking_url }}\">مشاهده رهگیری</a>\n@endif"
        );
        $tgSendToAdmins = (bool) ((int) Setting::get('shop_telegram_order_send_to_admins', 1));
        $tgSendToChannel = (bool) ((int) Setting::get('shop_telegram_order_send_to_channel', 1));
        $tgStatusSendToUser = (bool) ((int) Setting::get('shop_telegram_status_send_to_user', 1));

        // WhatsApp template (order status/tracking only)
        $whatsappOrderUpdateTemplate = Setting::get('shop_whatsapp_order_update_template',
            "🔔 بروزرسانی سفارش #{{ \$order->id }}\n"
            ."⏳ وضعیت: {{ \$order->status }}\n"
            ."@if(!empty(\$order->tracking_code))\n📦 کد رهگیری: {{ \$order->tracking_code }}\n@endif"
            ."@if(!empty(\$order->tracking_url))\n🔗 لینک رهگیری: {{ \$order->tracking_url }}\n@endif"
        );

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('settings')
            ->withVariables([
                'purchase_enabled' => $purchaseEnabled,
                'shipping_flat' => $shippingFlat,
                'enable_pricing_stock' => $enablePricingStock,
                'telegram_order_template' => $telegramOrderTemplate,
                'telegram_order_update_template' => $telegramOrderUpdateTemplate,
                'tg_send_admins' => $tgSendToAdmins,
                'tg_send_channel' => $tgSendToChannel,
                'tg_status_send_user' => $tgStatusSendToUser,
                'whatsapp_order_update_template' => $whatsappOrderUpdateTemplate,
            ]);
        return $this->view();
    }

    public function update(Request $request, string|int $id = 0): RedirectResponse
    {
        $rules = [
            // Checkboxes may be absent when unchecked
            'purchase_enabled' => ['sometimes','boolean'],
            'enable_pricing_stock' => ['sometimes','boolean'],
            'shipping_flat' => ['required','string'],
            'telegram_order_template' => ['required','string','max:5000'],
            'telegram_order_update_template' => ['required','string','max:5000'],
            'tg_send_admins' => ['sometimes','boolean'],
            'tg_send_channel' => ['sometimes','boolean'],
            'tg_status_send_user' => ['sometimes','boolean'],
            'whatsapp_order_update_template' => ['required','string','max:2000'],
        ];
        $messages = [
            'required' => 'فیلد :attribute الزامی است.',
            'exists' => 'مقدار :attribute نامعتبر است.',
        ];
        $attributes = [
            'purchase_enabled' => 'فعال بودن خرید',
            'shipping_flat' => 'هزینه حمل ثابت',
            'telegram_order_template' => 'تمپلیت تلگرام سفارش جدید',
            'telegram_order_update_template' => 'تمپلیت تلگرام بروزرسانی سفارش',
            'tg_send_admins' => 'ارسال به ادمین‌ها (تلگرام)',
            'tg_send_channel' => 'ارسال به کانال تلگرام',
            'tg_status_send_user' => 'ارسال به کاربر در تلگرام',
            'whatsapp_order_update_template' => 'تمپلیت واتس‌اپ بروزرسانی سفارش',
        ];
        $data = $request->validate($rules, $messages, $attributes);

        // Persist settings
        Setting::set('shop_purchase_enabled', $request->boolean('purchase_enabled') ? '1' : '0');
        Setting::set('shop_enable_pricing_stock_management', $request->boolean('enable_pricing_stock') ? '1' : '0');

        // Normalize shipping (remove separators and non-digits)
        $raw = (string) $request->input('shipping_flat', '0');
        $normalized = preg_replace('/[^0-9]/', '', $raw) ?: '0';
        Setting::set('shop_shipping_flat', $normalized);

        Setting::set('shop_telegram_order_template', (string)$data['telegram_order_template']);
        Setting::set('shop_telegram_order_update_template', (string)$data['telegram_order_update_template']);

        Setting::set('shop_telegram_order_send_to_admins', $request->boolean('tg_send_admins') ? '1' : '0');
        Setting::set('shop_telegram_order_send_to_channel', $request->boolean('tg_send_channel') ? '1' : '0');
        Setting::set('shop_telegram_status_send_to_user', $request->boolean('tg_status_send_user') ? '1' : '0');

        Setting::set('shop_whatsapp_order_update_template', (string) $data['whatsapp_order_update_template']);

        Setting::clearCache();

        return redirect()->route('admin.shop.settings.edit')->with('success','تنظیمات شاپ با موفقیت ذخیره شد');
    }
}
