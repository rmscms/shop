<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;
use RMS\Core\Models\Setting;
use RMS\Payment\DTO\PaymentRequest;
use RMS\Payment\Facades\Payment;
use RMS\Shop\Events\OrderPlacedEvent;
use RMS\Shop\Http\Resources\Admin\OrderResource;
use RMS\Shop\Http\Resources\Admin\OrderItemResource;
use RMS\Shop\Models\CheckoutIntent;
use RMS\Shop\Models\Order;
use RMS\Shop\Models\OrderAdminNote;
use RMS\Shop\Services\OrderAdminService;
use RMS\Shop\Services\OrderFinanceService;

class OrderController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/orders",
     *     tags={"Admin - Orders"},
     *     summary="List all orders",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Orders collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdminOrderResource")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginatedMeta")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = Order::query()
            ->select('orders.*')
            ->selectRaw('(SELECT COUNT(*) FROM order_items WHERE order_items.order_id = orders.id) as items_count')
            ->leftJoin('users', 'orders.user_id', '=', 'users.id')
            ->addSelect('users.name as user_name');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('orders.id', 'like', "%{$search}%")
                    ->orWhere('users.name', 'like', "%{$search}%")
                    ->orWhere('orders.tracking_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('orders.status', $request->input('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('orders.user_id', (int) $request->input('user_id'));
        }

        $orders = $query->orderByDesc('orders.id')->paginate($perPage);

        $data = OrderResource::collection($orders->items())->toArray($request);

        return $this->apiSuccess($data, [
            'pagination' => $this->paginationMeta($orders),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/orders/{id}",
     *     tags={"Admin - Orders"},
     *     summary="Show order detail",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Order detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminOrderResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(int $id)
    {
        $order = Order::with(['user', 'items.product'])->findOrFail($id);

        return $this->apiSuccess((new OrderResource($order))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/orders/{id}/update-status",
     *     tags={"Admin - Orders"},
     *     summary="Update order status",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", example="preparing")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order status updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminOrderResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,preparing,shipped,delivered,returned,rejected,paid,cancelled'],
        ]);

        OrderAdminService::updateStatus($id, $validated['status'], null);

        return $this->apiSuccess((new OrderResource(Order::findOrFail($id)))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/orders/{id}/tracking",
     *     tags={"Admin - Orders"},
     *     summary="Update order tracking",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="tracking_code", type="string", nullable=true),
     *             @OA\Property(property="tracking_url", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tracking updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminOrderResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function updateTracking(Request $request, int $id)
    {
        $validated = $request->validate([
            'tracking_code' => ['nullable', 'string', 'max:190'],
            'tracking_url' => ['nullable', 'string', 'max:500'],
        ]);

        OrderAdminService::updateTracking($id, $validated['tracking_code'] ?? null, $validated['tracking_url'] ?? null);

        return $this->apiSuccess((new OrderResource(Order::findOrFail($id)))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/orders/{id}/discount",
     *     tags={"Admin - Orders"},
     *     summary="Apply discount to order",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", format="float", example=10000),
     *             @OA\Property(property="note", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Discount applied",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminOrderResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function applyDiscount(Request $request, int $id)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        OrderAdminService::applyDiscount($id, (float) $validated['amount'], $validated['note'] ?? null, 0);

        return $this->apiSuccess((new OrderResource(Order::findOrFail($id)))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/orders/{id}/notes",
     *     tags={"Admin - Orders"},
     *     summary="Add note to order",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(property="content", type="string", example="Note content"),
     *             @OA\Property(property="visible_to_user", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Note added",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object", @OA\Property(property="note_id", type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function addNote(Request $request, int $id)
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'content' => ['required_without:note_text', 'nullable', 'string', 'max:5000'],
            'note_text' => ['required_without:content', 'nullable', 'string', 'max:5000'],
            'visible_to_user' => ['boolean'],
        ]);

        $note = OrderAdminNote::query()->create([
            'order_id' => $order->id,
            'admin_id' => null,
            'note_text' => (string) ($validated['note_text'] ?? $validated['content']),
            'visible_to_user' => (bool) ($validated['visible_to_user'] ?? false),
        ]);

        return $this->apiSuccess(['note_id' => (int) $note->id], [], 201);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/orders/{id}/invoice",
     *     tags={"Admin - Orders"},
     *     summary="Invoice data for an order",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Invoice JSON"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function invoice(int $id)
    {
        $order = DB::table('orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->select('o.*', 'u.name as user_name')
            ->where('o.id', $id)
            ->first();

        if (!$order) {
            return $this->apiError(['order' => ['سفارش پیدا نشد.']], 404);
        }

        $rows = DB::table('order_items')->where('order_id', (int) $order->id)->get();
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'description' => (string) ($row->item_name ?: (DB::table('products')->where('id', $row->product_id)->value('name') ?: ('#'.$row->product_id))),
                'quantity' => (int) $row->qty,
                'unit_price' => (float) $row->unit_price,
                'total' => (float) $row->total,
            ];
        }

        return $this->apiSuccess([
            'title' => 'فاکتور فروش',
            'invoice_number' => 'ORD-'.$order->id,
            'invoice_date' => $this->formatOrderDate($order->created_at),
            'seller' => [
                'company' => (string) config('app.name'),
                'address' => (string) config('company.address', ''),
                'postal_code' => (string) config('company.postal_code', ''),
                'phone' => (string) config('company.phone', ''),
            ],
            'buyer' => [
                'name' => (string) ($order->shipping_name ?: ($order->user_name ?? '')),
                'address' => (string) ($order->shipping_address ?: ''),
                'postal_code' => (string) ($order->shipping_postal_code ?: ''),
                'phone' => (string) ($order->shipping_mobile ?: ''),
            ],
            'items' => $items,
            'total_services' => (float) $order->subtotal,
            'postage_cost' => (float) $order->shipping_cost,
            'discount' => (float) $order->discount,
            'final_amount' => (float) $order->total,
            'bank' => [
                'account_holder' => (string) config('company.bank.account_holder', config('app.name')),
                'card_number' => (string) config('company.bank.card_number', ''),
                'sheba' => (string) config('company.bank.sheba', ''),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/orders/{id}/label",
     *     tags={"Admin - Orders"},
     *     summary="Shipping label data for an order",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Label JSON"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function label(int $id)
    {
        $order = DB::table('orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->select('o.*', 'u.name as user_name')
            ->where('o.id', $id)
            ->first();

        if (!$order) {
            return $this->apiError(['order' => ['سفارش پیدا نشد.']], 404);
        }

        return $this->apiSuccess([
            'order_id' => 'ORD-'.$order->id,
            'receiver' => [
                'name' => (string) ($order->shipping_name ?: ($order->user_name ?? '')),
                'phone' => (string) ($order->shipping_mobile ?: ''),
                'postal_code' => (string) ($order->shipping_postal_code ?? ''),
                'address' => (string) ($order->shipping_address ?? ''),
            ],
            'sender' => [
                'company' => (string) config('company.name', config('app.name')),
                'address' => (string) config('company.address', ''),
                'postal_code' => (string) config('company.postal_code', ''),
                'phone' => (string) config('company.phone', ''),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/orders/{id}/whatsapp",
     *     tags={"Admin - Orders"},
     *     summary="WhatsApp message and URL for an order update",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="WhatsApp payload"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function whatsapp(int $id)
    {
        $order = DB::table('orders')->where('id', $id)->first();
        if (!$order) {
            return $this->apiError(['order' => ['سفارش پیدا نشد.']], 404);
        }

        $user = DB::table('users')->where('id', (int) $order->user_id)->first();
        $phone = (string) ($order->shipping_mobile ?? ($user->mobile ?? ''));
        $phone = preg_replace('/\D+/', '', $phone ?? '');
        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }
        if ($phone !== '' && !str_starts_with($phone, '98')) {
            $phone = '98'.$phone;
        }

        $defaultTpl = <<<'BLADE'
🔔 بروزرسانی سفارش #{{ $order->id }}
⏳ وضعیت: {{ $order->status }}
@if(!empty($order->tracking_code))
📦 کد رهگیری: {{ $order->tracking_code }}
@endif
@if(!empty($order->tracking_url))
🔗 لینک رهگیری: {{ $order->tracking_url }}
@endif
BLADE;
        $tpl = class_exists(Setting::class)
            ? (string) Setting::get('shop_whatsapp_order_update_template', '')
            : '';
        if (trim($tpl) === '') {
            $tpl = $defaultTpl;
        }

        try {
            $message = Blade::render($tpl, ['order' => $order]);
        } catch (\Throwable $e) {
            $message = Blade::render($defaultTpl, ['order' => $order]);
        }

        $url = 'https://api.whatsapp.com/send?phone='.$phone.'&text='.rawurlencode($message);

        return $this->apiSuccess([
            'phone' => $phone,
            'message' => $message,
            'url' => $url,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/orders/{id}/points",
     *     tags={"Admin - Orders"},
     *     summary="Apply loyalty points for an order",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Points applied"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function applyPoints(Request $request, int $id)
    {
        Order::findOrFail($id);
        $validated = $request->validate([
            'points' => ['nullable', 'integer', 'min:0'],
        ]);

        $calculated = (int) DB::table('order_items')->where('order_id', $id)->sum('points_awarded');
        $requested = (int) ($validated['points'] ?? 0);
        $points = $requested > 0 ? $requested : $calculated;

        OrderAdminService::applyPoints($id, $points);

        return $this->apiSuccess(['points' => $points]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/orders/{id}/charge",
     *     tags={"Admin - Orders"},
     *     summary="Charge an order from credit or as debt",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Charged"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function charge(Request $request, int $id)
    {
        $order = Order::findOrFail($id);
        $validated = $request->validate([
            'mode' => ['required', 'in:use_credit,add_debt'],
        ]);

        if (!empty($order->paid_at)) {
            return $this->apiError(['order' => ['این سفارش قبلاً پرداخت شده است.']], 422);
        }
        if (!empty($order->refunded_at)) {
            return $this->apiError(['order' => ['این سفارش برگشت یا لغو شده است.']], 422);
        }

        $finance = app(OrderFinanceService::class)->charge($order, $validated['mode'], 0);

        return $this->apiSuccess([
            'finance_id' => (int) $finance->id,
            'mode' => $validated['mode'],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/admin/shop/orders/{id}/notes/{note}",
     *     tags={"Admin - Orders"},
     *     summary="Update an order note",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="note", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function updateNote(Request $request, int $id, int $note)
    {
        $row = OrderAdminNote::query()->where('order_id', $id)->where('id', $note)->first();
        if (!$row) {
            return $this->apiError(['note' => ['یادداشت پیدا نشد.']], 404);
        }

        $validated = $request->validate([
            'content' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'note_text' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'visible_to_user' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('note_text', $validated) || array_key_exists('content', $validated)) {
            $row->note_text = (string) ($validated['note_text'] ?? $validated['content'] ?? '');
        }
        if (array_key_exists('visible_to_user', $validated)) {
            $row->visible_to_user = (bool) $validated['visible_to_user'];
        }
        $row->save();

        return $this->apiSuccess([
            'id' => (int) $row->id,
            'note_text' => $row->note_text,
            'visible_to_user' => (bool) $row->visible_to_user,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/orders/{id}/notes/{note}",
     *     tags={"Admin - Orders"},
     *     summary="Delete an order note",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="note", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function deleteNote(int $id, int $note)
    {
        $deleted = OrderAdminNote::query()->where('order_id', $id)->where('id', $note)->delete();
        if (!$deleted) {
            return $this->apiError(['note' => ['یادداشت پیدا نشد.']], 404);
        }

        return $this->apiSuccess(['deleted' => true]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/orders/{id}/retry-sync",
     *     tags={"Admin - Orders"},
     *     summary="Dispatch the order placed event again",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Dispatched"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function retrySync(int $id)
    {
        Order::findOrFail($id);
        event(new OrderPlacedEvent($id));

        return $this->apiSuccess(['dispatched' => true]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/orders/{id}/retry-payment",
     *     tags={"Admin - Orders"},
     *     summary="Start payment again for an unpaid order",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payment redirect"),
     *     @OA\Response(response=422, description="Already paid"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function retryPayment(int $id)
    {
        $order = Order::findOrFail($id);
        if (!empty($order->paid_at)) {
            return $this->apiError(['payment' => ['این سفارش قبلاً پرداخت شده است.']], 422);
        }

        $driver = (string) ($order->payment_driver ?: '');
        if ($driver === '') {
            return $this->apiError(['payment' => ['درگاه پرداخت روی سفارش ثبت نشده است.']], 422);
        }

        if (!class_exists(PaymentRequest::class)) {
            return $this->apiError(['payment' => ['بسته پرداخت در دسترس نیست.']], 422);
        }

        $intent = CheckoutIntent::query()->where('order_id', $order->id)->latest('id')->first();
        $reference = $intent?->reference ?: ('ORD-'.$order->id);
        $callbackUrl = \Illuminate\Support\Facades\Route::has('shop.payment.callback')
            ? route('shop.payment.callback')
            : url('/payment/callback');

        $paymentRequest = new PaymentRequest(
            orderId: $reference,
            amount: (int) round((float) $order->total * (int) config('payment.amount_scale', 1)),
            currency: (string) config('payment.currency', 'IRT'),
            callbackUrl: $callbackUrl,
            customerName: $order->shipping_name,
            customerMobile: $order->shipping_mobile,
            description: 'پرداخت سفارش #'.$order->id,
            metadata: [
                'order_id' => (string) $order->id,
                'user_id' => (string) $order->user_id,
            ],
        );

        $result = Payment::start($paymentRequest, $driver);
        if (!$result->successful) {
            return $this->apiError(['payment' => [$result->message ?? 'payment_init_failed']], 422);
        }

        return $this->apiSuccess([
            'driver' => $driver,
            'reference' => $reference,
            'reference_id' => $result->referenceId,
            'redirect_url' => $result->redirectUrl,
            'form' => $result->formAction ? [
                'action' => $result->formAction,
                'method' => strtoupper($result->formMethod ?? 'POST'),
                'fields' => $result->formFields ?? [],
            ] : null,
        ]);
    }

    protected function formatOrderDate($value): string
    {
        if (function_exists('RMS\\Helper\\persian_date')) {
            return (string) \RMS\Helper\persian_date($value, 'Y/m/d');
        }

        return (string) $value;
    }
}
