<?php

namespace RMS\Shop\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderViewService
{
    public static function panelIndex(int $userId, int $page, int $perPage, ?string $statusFilter): array
    {
        $q = DB::table('orders')->where('user_id', $userId);
        if ($statusFilter) {
            if ($statusFilter === 'active') {
                $cfg = (string) config('shop.active_order_statuses', 'pending,preparing,paid');
                $statuses = collect(explode(',', $cfg))->map(fn($s)=>trim($s))->filter()->values()->all();
                if (!empty($statuses)) { $q->whereIn('status', $statuses); }
            } else {
                $q->where('status', $statusFilter);
            }
        }
        $total = (clone $q)->count();
        $rows = $q->orderByDesc('id')->forPage($page, $perPage)->get();
        $map = \RMS\Shop\Models\Order::statuses();
        $items = [];
        foreach ($rows as $o) {
            $info = $map[$o->status] ?? (object)['label'=>$o->status,'class'=>'bg-secondary'];
            $items[] = [
                'id' => (int)$o->id,
                'status' => ['label' => $info->label, 'class' => $info->class],
                'subtotal' => (float)$o->subtotal,
                'discount' => (float)$o->discount,
                'shipping_cost' => (float)$o->shipping_cost,
                'total' => (float)$o->total,
                'created_at' => $o->created_at,
                'paid_at' => $o->paid_at,
            ];
        }
        $b = DB::table('orders')->where('user_id', $userId);
        $stats = [
            'count' => (int) (clone $b)->count(),
            'sum' => (float) (clone $b)->sum('total'),
            'paid' => (float) (clone $b)->whereNotNull('paid_at')->sum('total'),
            'unpaid' => (float) (clone $b)->whereNull('paid_at')->sum('total'),
        ];
        return compact('items','total','stats');
    }

    public static function panelShow(int $orderId, int $userId): array
    {
        $order = DB::table('orders')->where(['id'=>$orderId,'user_id'=>$userId])->first();
        abort_if(!$order,404);
        [$items, $pointsSum] = self::buildItemsAndPoints($orderId);
        $statusMap = \RMS\Shop\Models\Order::statuses();
        $info = $statusMap[$order->status] ?? (object)['label'=>$order->status, 'class'=>'bg-secondary'];
        $notes = DB::table('order_admin_notes')->where(['order_id'=>$orderId,'visible_to_user'=>1])->orderByDesc('id')->get(['note_text','created_at']);
        $applied = DB::table('user_point_logs')->where(['order_id'=>$orderId,'reason'=>'order'])->exists();
        $reversed = DB::table('user_point_logs')->where(['order_id'=>$orderId,'reason'=>'order_refund'])->exists();
        $appliedEffective = $applied && !$reversed;
        return [
            'order'=>$order,
            'items'=>$items,
            'status'=>['label'=>$info->label,'class'=>$info->class],
            'notes'=>$notes,
            'points_sum'=>$pointsSum,
            'points_applied'=>$appliedEffective,
        ];
    }

    public static function adminShow(int $orderId): array
    {
        $order = DB::table('orders as o')->leftJoin('users as u','u.id','=','o.user_id')
            ->select('o.*','u.name as user_name')->where('o.id',$orderId)->first();
        abort_if(!$order,404);
        [$items, $pointsSum] = self::buildItemsAndPoints($orderId);
        $statusMap = \RMS\Shop\Models\Order::statuses();
        $info = $statusMap[$order->status] ?? (object)['label'=>$order->status, 'class'=>'bg-secondary'];
        $notes = DB::table('order_admin_notes as n')->leftJoin('admins as a','a.id','=','n.admin_id')
            ->where('n.order_id',$orderId)->orderByDesc('n.id')->get(['n.id','n.note_text','n.visible_to_user','n.created_at','a.name as admin_name']);
        $userPoints = (int) (DB::table('user_points')->where('user_id',(int)$order->user_id)->value('total_points') ?? 0);
        $pointsApplied = DB::table('user_point_logs')->where(['order_id'=>$orderId,'reason'=>'order'])->exists();
        return [
            'order'=>$order,
            'items'=>$items,
            'status'=>['label'=>$info->label,'class'=>$info->class],
            'notes'=>$notes,
            'points_sum'=>$pointsSum,
            'points_applied'=>$pointsApplied,
            'user_points_total'=>$userPoints,
        ];
    }

    private static function buildItemsAndPoints(int $orderId): array
    {
        $rows = DB::table('order_items as oi')
            ->leftJoin('products as p','p.id','=','oi.product_id')
            ->leftJoin('product_combinations as pc','pc.id','=','oi.combination_id')
            ->where('oi.order_id',$orderId)
            ->select('oi.*','p.name as product_name','pc.sku as sku','oi.item_name','oi.item_attributes')
            ->orderBy('oi.id')->get();
        $items = [];
        foreach ($rows as $it) {
            $label = $it->item_attributes
                ? self::formatAttributeList($it->item_attributes)
                : ($it->combination_id ? self::combinationLabel((int) $it->combination_id) : null);
            $thumb = null; $thumbAvif = null; $fullUrl = null; $fullAvif = null;
            if ($it->combination_id) {
                $t = self::thumbnailForCombination((int) $it->combination_id);
                if ($t) { $thumb = $t['avif_url'] ?: $t['url']; $thumbAvif = $t['avif_url'] ?? null; $fullUrl = $t['url'] ?? null; $fullAvif = $t['avif_url'] ?? null; }
            }
            if (!$thumb) {
                $t = self::thumbnailForProduct((int) $it->product_id);
                if ($t) { $thumb = $t['avif_url'] ?: $t['url']; $thumbAvif = $t['avif_url'] ?? null; $fullUrl = $t['url'] ?? null; $fullAvif = $t['avif_url'] ?? null; }
            }
            $nameSnap = $it->item_name ?: null;
            $prodName = (string)($it->product_name ?? ('#'.$it->product_id));
            $items[] = [
                'id' => (int)$it->id,
                'name' => (string)($nameSnap ?: ($label ? ($prodName.' - '.$label) : $prodName)),
                'sku' => (string)($it->sku ?? ''),
                'attributes' => $label,
                'qty' => (int)$it->qty,
                'unit_price' => (float)$it->unit_price,
                'total' => (float)$it->total,
                'thumb' => $thumb,
                'image_url' => $fullUrl,
                'image_avif' => $fullAvif,
            ];
        }
        $pointsSum = (int) DB::table('order_items')->where('order_id',$orderId)->sum('points_awarded');
        return [$items, $pointsSum];
    }

    protected static function formatAttributeList($raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $array = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($array)) {
            return null;
        }

        return collect($array)
            ->map(function ($row) {
                if (is_array($row)) {
                    $title = trim((string) ($row['title'] ?? ''));
                    $value = trim((string) ($row['value'] ?? ''));
                    if ($title !== '' && $value !== '') {
                        return $title.': '.$value;
                    }
                    return $title ?: $value;
                }
                if (is_string($row)) {
                    return trim($row);
                }
                return null;
            })
            ->filter()
            ->implode(' | ');
    }

    protected static function combinationLabel(int $combinationId): ?string
    {
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

        if ($rows->isEmpty()) {
            return null;
        }

        return $rows->map(function ($row) {
            $attr = trim((string) ($row->attribute_name ?? ''));
            $value = trim((string) ($row->value ?? ''));
            if ($attr !== '' && $value !== '') {
                return $attr.': '.$value;
            }
            return $attr ?: $value;
        })->filter()->implode(' / ');
    }

    protected static function thumbnailForCombination(int $combinationId): ?array
    {
        $path = DB::table('product_combination_images')
            ->where('combination_id', $combinationId)
            ->orderByDesc('is_main')
            ->orderBy('sort')
            ->value('path');

        return self::formatImageVariant($path);
    }

    protected static function thumbnailForProduct(int $productId): ?array
    {
        $path = DB::table('product_images')
            ->where('product_id', $productId)
            ->orderByDesc('is_main')
            ->orderBy('sort')
            ->value('path');

        return self::formatImageVariant($path);
    }

    protected static function formatImageVariant(?string $relativePath): ?array
    {
        if (!$relativePath || !Storage::disk('public')->exists($relativePath)) {
            return null;
        }

        $url = Storage::disk('public')->url($relativePath);
        $avifUrl = null;
        if (Str::contains($relativePath, '/orig/')) {
            $dir = Str::beforeLast($relativePath, '/orig/');
            $name = Str::afterLast($relativePath, '/orig/');
            $base = pathinfo($name, PATHINFO_FILENAME);
            $avifRel = $dir.'/avif/'.$base.'.avif';
            if (Storage::disk('public')->exists($avifRel)) {
                $avifUrl = Storage::disk('public')->url($avifRel);
            }
        }

        return [
            'url' => $url,
            'avif_url' => $avifUrl,
        ];
    }
}


