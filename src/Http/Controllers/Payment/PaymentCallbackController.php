<?php

namespace RMS\Shop\Http\Controllers\Payment;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RMS\Payment\Facades\Payment;
use RMS\Payment\Models\PaymentTransaction;
use RMS\Shop\Events\OrderPlacedEvent;
use RMS\Shop\Events\StockDecreased;
use RMS\Shop\Models\CheckoutIntent;
use RMS\Shop\Models\Order;
use RMS\Shop\Models\OrderItem;
use RMS\Shop\Services\CartReservationService;
use RMS\Shop\Services\ShopPriceService;
use RMS\Shop\Support\PanelApi\CartStorage;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductCombination;
use Illuminate\Support\Str;

class PaymentCallbackController extends Controller
{
    protected array $productNameCache = [];
    protected array $combinationAttributeCache = [];
    protected array $combinationImageCache = [];
    protected array $productImageCache = [];

    public function __construct(
        protected CartReservationService $reservations,
        protected CartStorage $storage
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $reference = $request->query('payment_order', $request->input('payment_order'));
        $driver = $request->query('payment_driver', $request->input('payment_driver')) ?: config('payment.default');

        if (!$reference) {
            return redirect()->to($this->redirectUrl('failed', null, [
                'message' => 'missing_reference',
            ]));
        }

        try {
            $verification = Payment::verify($request->all(), $driver);
        } catch (\Throwable $e) {
            Log::error('Payment verification failed', [
                'message' => $e->getMessage(),
                'reference' => $reference,
                'driver' => $driver,
            ]);

            return redirect()->to($this->redirectUrl('failed', null, [
                'message' => 'verification_error',
                'reference' => $reference,
            ]));
        }

        $intent = CheckoutIntent::where('reference', $reference)->first();

        if (!$intent) {
            return redirect()->to($this->redirectUrl('failed', null, [
                'message' => 'intent_not_found',
                'reference' => $reference,
            ]));
        }

        if ($intent->status === CheckoutIntent::STATUS_SUCCESS && $intent->order_id) {
            $order = Order::find($intent->order_id);

            return redirect()->to($this->redirectUrl('success', $order, [
                'payment_status' => 'success',
                'order' => $order?->id,
            ]));
        }

        $transaction = PaymentTransaction::query()
            ->where('order_id', $reference)
            ->orWhere('authority', $request->input('Authority') ?? $request->input('authority'))
            ->latest()
            ->first();

        if (!$transaction) {
            return redirect()->to($this->redirectUrl('failed', null, [
                'message' => 'transaction_not_found',
                'reference' => $reference,
            ]));
        }

        if (!$verification->successful) {
            $intent->status = CheckoutIntent::STATUS_FAILED;
            $intent->save();

            return redirect()->to($this->redirectUrl('failed', null, [
                'payment_status' => 'failed',
                'reference' => $reference,
                'message' => $verification->message ?? 'پرداخت ناموفق بود',
            ]));
        }

        $order = $this->createOrderFromIntent($intent, $transaction);

        $order->forceFill([
            'status' => config('shop.payment.success_status', 'paid'),
            'paid_at' => now(),
            'payment_reference' => $verification->transactionId ?: $verification->referenceId ?: $order->payment_reference,
        ])->save();

        $intent->status = CheckoutIntent::STATUS_SUCCESS;
        $intent->order_id = $order->id;
        $intent->save();

        if (!empty($intent->cart_id)) {
            $this->reservations->releaseCart((int) $intent->cart_id);
        }

        if (!empty($intent->cart_key)) {
            $this->storage->putItems($intent->cart_key, []);
        }

        event(new OrderPlacedEvent((int) $order->id));

        return redirect()->to($this->redirectUrl('success', $order, [
            'payment_status' => 'success',
            'order' => $order->id,
        ]));
    }

    protected function createOrderFromIntent(CheckoutIntent $intent, PaymentTransaction $transaction): Order
    {
        return DB::transaction(function () use ($intent, $transaction) {
            $pricing = $intent->pricing_snapshot ?? [];
            $address = $intent->address_snapshot ?? [];
            $cart = $intent->cart_snapshot ?? [];

            $order = Order::create([
                'user_id' => $intent->user_id,
                'user_address_id' => $address['id'] ?? null,
                'status' => config('shop.default_status', 'pending'),
                'subtotal' => $pricing['subtotal'] ?? 0,
                'discount' => $pricing['discount'] ?? 0,
                'shipping_cost' => $pricing['shipping_cost'] ?? 0,
                'total' => $pricing['total'] ?? 0,
                'shipping_name' => $address['full_name'] ?? null,
                'shipping_mobile' => $address['mobile'] ?? null,
                'shipping_postal_code' => $address['postal_code'] ?? null,
                'shipping_address' => $address['address_line'] ?? null,
                'customer_note' => $intent->customer_note,
                'payment_driver' => $intent->payment_driver,
            ]);

            $priceService = app(ShopPriceService::class);
            foreach ($cart['items'] ?? [] as $line) {
                $attributes = $this->buildAttributeSnapshot($line['combination_id'] ?? null);
                $itemName = $this->buildItemName($line, $attributes);
                $unitPrice = isset($line['unit_price']) ? (float) $line['unit_price'] : 0.0;
                $unitPriceCny = isset($line['unit_price_cny']) ? (float) $line['unit_price_cny'] : null;
                if ($unitPrice <= 0 && $unitPriceCny !== null) {
                    $cacheContext = 'order:'.$order->id.':'.($line['line_id'] ?? ($line['product_id'] ?? 'item'));
                    $converted = $priceService->convertFromBase($unitPriceCny, $cacheContext);
                    if ($converted !== null) {
                        $unitPrice = $converted;
                    }
                }
                $lineSubtotal = isset($line['subtotal']) ? (float) $line['subtotal'] : round($unitPrice * (int) ($line['qty'] ?? 1), 2);
                $rateValue = $line['rate']['rate'] ?? $pricing['rate']['rate'] ?? null;
                $imageSnapshot = $this->buildImageSnapshot($line);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product_id'],
                    'combination_id' => $line['combination_id'],
                    'image_id' => $imageSnapshot['image_id'] ?? null,
                    'image_snapshot' => $imageSnapshot,
                    'qty' => $line['qty'],
                    'unit_price' => $unitPrice,
                    'total' => $lineSubtotal,
                    'unit_price_cny' => $unitPriceCny,
                    'rate_cny_to_irt' => $rateValue,
                    'points_awarded' => 0,
                    'item_name' => $itemName,
                    'item_attributes' => $attributes ? json_encode($attributes, JSON_UNESCAPED_UNICODE) : null,
                ]);

                // Decrease stock for the purchased item
                $qty = (int) ($line['qty'] ?? 1);
                $previousStock = 0;
                $newStock = 0;

                if ($line['combination_id']) {
                    // Decrease combination stock
                    $combination = DB::table('product_combinations')
                        ->where('id', $line['combination_id'])
                        ->first();

                    if ($combination) {
                        $previousStock = (int) $combination->stock_qty;
                        $newStock = max(0, $previousStock - $qty);

                        DB::table('product_combinations')
                            ->where('id', $line['combination_id'])
                            ->update(['stock_qty' => $newStock, 'updated_at' => now()]);

                        // Fire event
                        event(new StockDecreased(
                            orderId: $order->id,
                            productId: (int) $line['product_id'],
                            combinationId: (int) $line['combination_id'],
                            quantityDecreased: $qty,
                            previousStock: $previousStock,
                            newStock: $newStock
                        ));
                    }
                } else {
                    // Decrease product stock
                    $product = DB::table('products')
                        ->where('id', $line['product_id'])
                        ->first();

                    if ($product) {
                        $previousStock = (int) $product->stock_qty;
                        $newStock = max(0, $previousStock - $qty);

                        DB::table('products')
                            ->where('id', $line['product_id'])
                            ->update(['stock_qty' => $newStock, 'updated_at' => now()]);

                        // Fire event
                        event(new StockDecreased(
                            orderId: $order->id,
                            productId: (int) $line['product_id'],
                            combinationId: null,
                            quantityDecreased: $qty,
                            previousStock: $previousStock,
                            newStock: $newStock
                        ));
                    }
                }
            }

            if (!empty($intent->cart_id)) {
                DB::table('cart_items')->where('cart_id', $intent->cart_id)->delete();
                DB::table('carts')->where('id', $intent->cart_id)->update([
                    'status' => 'converted',
                    'updated_at' => now(),
                ]);
            }

            $transaction->order_id = (string) $order->id;
            $transaction->save();

            $order->refresh();

            return $order;
        });
    }

    protected function redirectUrl(string $type, ?Order $order, array $params = []): string
    {
        $template = config("shop.payment.redirects.{$type}", '/');
        $orderId = $order?->id ?? ($params['reference'] ?? '0');

        $url = str_replace('{order}', (string) $orderId, $template);
        if (empty($params)) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($params);
    }

    protected function buildItemName(array $line, array $attributes): string
    {
        $productId = (int) ($line['product_id'] ?? 0);
        $cachedName = $line['product']['name'] ?? null;

        if (!$cachedName) {
            if (!isset($this->productNameCache[$productId])) {
                $this->productNameCache[$productId] = DB::table('products')->where('id', $productId)->value('name');
            }
            $cachedName = $this->productNameCache[$productId] ?? ('محصول #' . $productId);
        }

        if (empty($attributes)) {
            return (string) $cachedName;
        }

        $attributeSummary = collect($attributes)
            ->map(fn ($attr) => trim(($attr['title'] ?? '') . ': ' . ($attr['value'] ?? '')))
            ->filter()
            ->implode(' | ');

        return trim($cachedName . ($attributeSummary ? ' - ' . $attributeSummary : ''));
    }

    protected function buildAttributeSnapshot($combinationId): array
    {
        $combinationId = $combinationId ? (int) $combinationId : null;
        if (!$combinationId) {
            return [];
        }

        if (array_key_exists($combinationId, $this->combinationAttributeCache)) {
            return $this->combinationAttributeCache[$combinationId];
        }

        $rows = DB::table('product_combination_values as v')
            ->join('product_attribute_values as pav', 'pav.id', '=', 'v.attribute_value_id')
            ->leftJoin('product_attributes as pa', 'pa.id', '=', 'pav.attribute_id')
            ->where('v.combination_id', $combinationId)
            ->orderBy('pa.sort')
            ->orderBy('pav.sort')
            ->get([
                'pa.name as attribute_name',
                'pav.value as value',
            ]);

        $attributes = $rows->map(function ($row) {
            return [
                'title' => (string) ($row->attribute_name ?? ''),
                'value' => (string) ($row->value ?? ''),
            ];
        })->filter(fn ($attr) => $attr['title'] !== '' || $attr['value'] !== '')->values()->all();

        return $this->combinationAttributeCache[$combinationId] = $attributes;
    }

    protected function buildImageSnapshot(array $line): ?array
    {
        $combinationId = $line['combination_id'] ?? null;
        if ($combinationId) {
            $snapshot = $this->combinationImageSnapshot((int) $combinationId);
            if ($snapshot) {
                return $snapshot;
            }
        }

        return $this->productImageSnapshot((int) $line['product_id']);
    }

    protected function combinationImageSnapshot(int $combinationId): ?array
    {
        if (array_key_exists($combinationId, $this->combinationImageCache)) {
            return $this->combinationImageCache[$combinationId];
        }

        $assignment = $this->fetchAssignedImage(ProductCombination::class, $combinationId);
        if ($assignment) {
            return $this->combinationImageCache[$combinationId] = $assignment;
        }

        $legacyPath = DB::table('product_combination_images')
            ->where('combination_id', $combinationId)
            ->orderByDesc('is_main')
            ->orderBy('sort')
            ->value('path');

        if ($legacyPath) {
            return $this->combinationImageCache[$combinationId] = [
                'image_id' => null,
                'disk' => 'public',
                'path' => $legacyPath,
                'avif_path' => $this->deriveAvifPath($legacyPath),
                'source' => 'legacy_combination',
            ];
        }

        return $this->combinationImageCache[$combinationId] = null;
    }

    protected function productImageSnapshot(int $productId): ?array
    {
        if (array_key_exists($productId, $this->productImageCache)) {
            return $this->productImageCache[$productId];
        }

        $assignment = $this->fetchAssignedImage(Product::class, $productId);
        if ($assignment) {
            return $this->productImageCache[$productId] = $assignment;
        }

        $legacyPath = DB::table('product_images')
            ->where('product_id', $productId)
            ->orderByDesc('is_main')
            ->orderBy('sort')
            ->value('path');

        if ($legacyPath) {
            return $this->productImageCache[$productId] = [
                'image_id' => null,
                'disk' => 'public',
                'path' => $legacyPath,
                'avif_path' => $this->deriveAvifPath($legacyPath),
                'source' => 'legacy_product',
            ];
        }

        return $this->productImageCache[$productId] = null;
    }

    protected function fetchAssignedImage(string $assignableType, int $assignableId): ?array
    {
        try {
            $row = DB::table('image_assignments as ia')
                ->join('image_library as il', 'il.id', '=', 'ia.image_id')
                ->where('ia.assignable_type', $assignableType)
                ->where('ia.assignable_id', $assignableId)
                ->orderByDesc('ia.is_main')
                ->orderBy('ia.sort')
                ->select('ia.image_id', 'il.path')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        if (!$row) {
            return null;
        }

        return [
            'image_id' => (int) $row->image_id,
            'disk' => 'public',
            'path' => $row->path,
            'avif_path' => $this->deriveAvifPath($row->path),
            'source' => 'library',
        ];
    }

    protected function deriveAvifPath(?string $relativePath): ?string
    {
        if (!$relativePath || !Str::contains($relativePath, '/orig/')) {
            return null;
        }

        $directory = Str::beforeLast($relativePath, '/orig/');
        $filename = Str::afterLast($relativePath, '/orig/');
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        return $directory.'/avif/'.$baseName.'.avif';
    }
}

