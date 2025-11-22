<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;
use RMS\Core\Models\Setting;
use RMS\Payment\DTO\PaymentRequest;
use RMS\Payment\Facades\Payment;
use RMS\Payment\Models\PaymentDriver;
use RMS\Shop\Http\Requests\Api\Panel\CheckoutRequest;
use RMS\Shop\Models\CheckoutIntent;
use RMS\Shop\Models\UserAddress;
use RMS\Shop\Services\ShopPriceService;
use RMS\Shop\Support\PanelApi\CartManager;
use RMS\Shop\Support\PanelApi\CartStorage;

class CheckoutController extends BaseController
{
    public function __construct(
        protected CartManager $cartManager,
        protected CartStorage $storage,
        protected ShopPriceService $priceService
    ) {
    }

    /**
     * @OA\Post(
     *     path="/checkout",
     *     tags={"Orders"},
     *     summary="Submit cart and create order",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CheckoutPayload")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/OrderDetailResource"),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="cleared_cart", type="boolean")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        [$cartKey, $cookie] = $this->storage->resolveCartKey($request);
        $userId = (int) $request->user()->id;
        $address = UserAddress::where('user_id', $userId)->findOrFail((int) $request->input('address_id'));
        $driverKey = $request->string('payment_driver');

        $driver = PaymentDriver::query()
            ->where('driver', $driverKey)
            ->where('is_active', true)
            ->first();

        if (!$driver) {
            return $this->apiError(['payment_driver' => ['درگاه پرداخت انتخابی در دسترس نیست']], 422);
        }

        $cartPayload = $this->cartManager->buildCartPayload($cartKey);
        if (empty($cartPayload['items'])) {
            return $this->apiError(['cart' => ['سبد خرید خالی است']], 422);
        }

        $shippingCost = (float) Setting::get('shop_shipping_flat', 0);
        $subtotal = (float) $cartPayload['summary']['total_amount'];
        $subtotalCny = $cartPayload['summary']['total_amount_cny'] ?? null;
        $discount = 0.0;
        $total = max(0, $subtotal - $discount) + $shippingCost;
        $shippingCostCny = $this->priceService->convertToBase($shippingCost, $cartPayload['rate'] ?? null);
        $totalCny = $subtotalCny !== null
            ? round($subtotalCny - $discount + ($shippingCostCny ?? 0), 4)
            : null;

        $cart = $this->cartManager->syncToUserCart($cartKey, $userId);

        $intent = CheckoutIntent::create([
            'reference' => (string) Str::uuid(),
            'user_id' => $userId,
            'cart_id' => $cart?->id,
            'cart_key' => $cartKey,
            'payment_driver' => $driverKey,
            'cart_snapshot' => $cartPayload,
            'address_snapshot' => [
                'id' => $address->id,
                'full_name' => $address->full_name,
                'mobile' => $address->mobile,
                'postal_code' => $address->postal_code,
                'address_line' => $address->address_line,
                'province_id' => $address->province_id,
                'city' => $address->city,
            ],
            'pricing_snapshot' => [
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'subtotal_cny' => $subtotalCny,
                'discount_cny' => $discount > 0 ? $this->priceService->convertToBase($discount, $cartPayload['rate'] ?? null) : null,
                'shipping_cost_cny' => $shippingCostCny,
                'total_cny' => $totalCny,
                'currency' => $cartPayload['currency'] ?? null,
                'rate' => $cartPayload['rate'] ?? null,
            ],
            'customer_note' => $request->input('customer_note'),
        ]);

        try {
            $paymentPayload = $this->initializePayment($request, $address, $intent, $driverKey);
        } catch (\Throwable $exception) {
            $intent->status = CheckoutIntent::STATUS_FAILED;
            $intent->save();
            $message = $exception->getMessage() ?: 'در راه‌اندازی درگاه پرداخت مشکلی رخ داد.';

            Log::error('Payment initialization failed', [
                'reference' => $intent->reference,
                'driver' => $driverKey,
                'message' => $message,
            ]);

            return $this->apiError(['payment' => [$message]], 422);
        }

        $response = $this->apiSuccess([
            'payment' => $paymentPayload,
            'reference' => $intent->reference,
        ]);

        if ($cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    protected function initializePayment(CheckoutRequest $request, UserAddress $address, CheckoutIntent $intent, string $driver): array
    {
        $callbackUrl = route('shop.payment.callback');

        $paymentRequest = new PaymentRequest(
            orderId: $intent->reference,
            amount: (int) round(($intent->pricing_snapshot['total'] ?? 0) * (int) config('payment.amount_scale', 1)),
            currency: config('payment.currency', 'IRT'),
            callbackUrl: $callbackUrl,
            customerName: $address->full_name,
            customerMobile: $address->mobile,
            customerEmail: $request->user()->email ?? null,
            description: 'پرداخت سفارش #' . $intent->reference,
            metadata: [
                'user_id' => (string) $request->user()->id,
                'checkout_reference' => $intent->reference,
            ],
        );

        $result = Payment::start($paymentRequest, $driver);

        if (!$result->successful) {
            throw new \RuntimeException($result->message ?? 'payment_init_failed');
        }

        return [
            'driver' => $driver,
            'reference_id' => $result->referenceId,
            'redirect_url' => $result->redirectUrl,
            'form' => $result->formAction ? [
                'action' => $result->formAction,
                'method' => strtoupper($result->formMethod ?? 'POST'),
                'fields' => $result->formFields ?? [],
            ] : null,
            'checkout_reference' => $intent->reference,
        ];
    }
}

