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

        // Storefront: SMS, OTP, Shahkar (Nitron)
        $shahkarEnabled = (bool) ((int) Setting::get('storefront_shahkar_enabled', env('STOREFRONT_SHAHKAR_ENABLED', true) ? '1' : '0'));
        $shahkarDriver = (string) Setting::get('storefront_shahkar_driver', env('STOREFRONT_SHAHKAR_DRIVER', 'fake'));
        $smsDriver = (string) Setting::get('storefront_sms_driver', env('STOREFRONT_SMS_DRIVER', 'log'));
        $nitronApiKeySet = (string) Setting::get('storefront_nitron_api_key', env('NITRON_SMS_API_KEY', '')) !== '';
        $nitronBaseUrl = (string) Setting::get('storefront_nitron_base_url', env('NITRON_SMS_BASE_URL', 'https://sms.nitron.pro/api'));
        $nitronOtpTemplate = (string) Setting::get('storefront_nitron_otp_template_code', env('NITRON_OTP_TEMPLATE_CODE', ''));
        $nitronOtpParam = (string) Setting::get('storefront_nitron_otp_parameter_key', env('NITRON_OTP_PARAMETER_KEY', 'code'));
        $otpShowOnScreen = (bool) ((int) Setting::get('storefront_otp_show_on_screen', env('STOREFRONT_OTP_ON_SCREEN', env('APP_ENV') !== 'production') ? '1' : '0'));

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
                'shahkar_enabled' => $shahkarEnabled,
                'shahkar_driver' => $shahkarDriver,
                'sms_driver' => $smsDriver,
                'nitron_api_key_set' => $nitronApiKeySet,
                'nitron_base_url' => $nitronBaseUrl,
                'nitron_otp_template_code' => $nitronOtpTemplate,
                'nitron_otp_parameter_key' => $nitronOtpParam,
                'otp_show_on_screen' => $otpShowOnScreen,
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
            'shahkar_enabled' => ['sometimes', 'boolean'],
            'shahkar_driver' => ['required', 'in:fake,nitron'],
            'sms_driver' => ['required', 'in:log,nitron'],
            'nitron_api_key' => ['nullable', 'string', 'max:500'],
            'nitron_base_url' => ['required', 'string', 'max:500'],
            'nitron_otp_template_code' => ['nullable', 'string', 'max:200'],
            'nitron_otp_parameter_key' => ['nullable', 'string', 'max:80'],
            'otp_show_on_screen' => ['sometimes', 'boolean'],
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
            'shahkar_enabled' => 'فعال بودن شاهکار',
            'shahkar_driver' => 'درایور شاهکار',
            'sms_driver' => 'درایور پیامک',
            'nitron_api_key' => 'کلید API Nitron',
            'nitron_base_url' => 'آدرس API Nitron',
            'nitron_otp_template_code' => 'کد تمپلیت OTP',
            'nitron_otp_parameter_key' => 'کلید پارامتر OTP',
            'otp_show_on_screen' => 'نمایش کد OTP روی صفحه',
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

        Setting::set('storefront_shahkar_enabled', $request->boolean('shahkar_enabled') ? '1' : '0');
        Setting::set('storefront_shahkar_driver', (string) $data['shahkar_driver']);
        Setting::set('storefront_sms_driver', (string) $data['sms_driver']);
        Setting::set('storefront_nitron_base_url', rtrim((string) $data['nitron_base_url'], '/'));
        Setting::set('storefront_nitron_otp_template_code', (string) ($data['nitron_otp_template_code'] ?? ''));
        Setting::set('storefront_nitron_otp_parameter_key', (string) ($data['nitron_otp_parameter_key'] ?? 'code'));
        Setting::set('storefront_otp_show_on_screen', $request->boolean('otp_show_on_screen') ? '1' : '0');

        $apiKey = trim((string) $request->input('nitron_api_key', ''));
        if ($apiKey !== '') {
            Setting::set('storefront_nitron_api_key', $apiKey);
        }

        Setting::clearCache();

        return redirect()->route('admin.shop.settings.edit')->with('success','تنظیمات شاپ با موفقیت ذخیره شد');
    }
}
