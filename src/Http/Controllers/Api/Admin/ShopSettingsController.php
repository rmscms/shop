<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Core\Models\Setting;

class ShopSettingsController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/settings",
     *     tags={"Admin - Settings"},
     *     summary="Get shop settings",
     *     security={{"admin":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Shop settings",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index()
    {
        $settings = [
            'purchase_enabled' => (bool) ((int) Setting::get('shop_purchase_enabled', 1)),
            'shipping_flat' => (float) (Setting::get('shop_shipping_flat', 0) ?? 0),
            'telegram_order_template' => Setting::get('shop_telegram_order_template', ''),
            'telegram_order_update_template' => Setting::get('shop_telegram_order_update_template', ''),
            'tg_send_admins' => (bool) ((int) Setting::get('shop_telegram_order_send_to_admins', 1)),
            'tg_send_channel' => (bool) ((int) Setting::get('shop_telegram_order_send_to_channel', 1)),
            'tg_status_send_user' => (bool) ((int) Setting::get('shop_telegram_status_send_to_user', 1)),
            'whatsapp_order_update_template' => Setting::get('shop_whatsapp_order_update_template', ''),
        ];

        return $this->apiSuccess($settings);
    }

    /**
     * @OA\Put(
     *     path="/admin/shop/settings",
     *     tags={"Admin - Settings"},
     *     summary="Update shop settings",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="purchase_enabled", type="boolean", nullable=true),
     *             @OA\Property(property="shipping_flat", type="number", format="float", nullable=true),
     *             @OA\Property(property="telegram_order_template", type="string", nullable=true),
     *             @OA\Property(property="telegram_order_update_template", type="string", nullable=true),
     *             @OA\Property(property="tg_send_admins", type="boolean", nullable=true),
     *             @OA\Property(property="tg_send_channel", type="boolean", nullable=true),
     *             @OA\Property(property="tg_status_send_user", type="boolean", nullable=true),
     *             @OA\Property(property="whatsapp_order_update_template", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'purchase_enabled' => ['sometimes', 'boolean'],
            'shipping_flat' => ['required', 'string'],
            'telegram_order_template' => ['required', 'string', 'max:5000'],
            'telegram_order_update_template' => ['required', 'string', 'max:5000'],
            'tg_send_admins' => ['sometimes', 'boolean'],
            'tg_send_channel' => ['sometimes', 'boolean'],
            'tg_status_send_user' => ['sometimes', 'boolean'],
            'whatsapp_order_update_template' => ['required', 'string', 'max:2000'],
        ]);

        Setting::set('shop_purchase_enabled', $validated['purchase_enabled'] ?? null);
        Setting::set('shop_shipping_flat', $validated['shipping_flat']);
        Setting::set('shop_telegram_order_template', $validated['telegram_order_template']);
        Setting::set('shop_telegram_order_update_template', $validated['telegram_order_update_template']);
        Setting::set('shop_telegram_order_send_to_admins', $validated['tg_send_admins'] ?? null);
        Setting::set('shop_telegram_order_send_to_channel', $validated['tg_send_channel'] ?? null);
        Setting::set('shop_telegram_status_send_to_user', $validated['tg_status_send_user'] ?? null);
        Setting::set('shop_whatsapp_order_update_template', $validated['whatsapp_order_update_template']);

        return $this->apiSuccess(['updated' => true]);
    }
}
