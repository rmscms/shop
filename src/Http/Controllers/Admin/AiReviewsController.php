<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RMS\Shop\Models\AiAction;
use RMS\Shop\Models\AiActivity;
use RMS\Shop\Models\AiReview;
use RMS\Shop\Models\AiRound;
use RMS\Shop\Models\Product;

/**
 * Product reviews done by an outside agent: admins request them here and read the logged result.
 */
class AiReviewsController extends ShopAdminController
{
    use \RMS\Shop\Http\Controllers\Admin\Concerns\ResolvesAiRound;

    public function table(): string
    {
        return 'shop_ai_reviews';
    }

    public function modelName(): string
    {
        return AiReview::class;
    }

    // Must match the core AdminController::index(Request) signature.
    public function index(Request $request)
    {
        $this->title('آمار بررسی AI محصولات');

        $ready = Schema::hasTable('shop_ai_reviews');
        $status = (string) $request->query('status', '');

        $stats = null;
        $reviews = collect();
        $unreviewed = collect();
        $actions = collect();
        $round = $this->aiRound($request);
        $rid = $round['id'];
        $inRound = fn ($query) => $rid ? $query->where('round_id', $rid) : $query;

        if ($ready) {
            if (Schema::hasTable('shop_ai_actions')) {
                $actions = $inRound(AiAction::query()->with('product:id,name'))->orderByDesc('id')->limit(15)->get();
            }

            $total = Product::query()->count();
            $reviewedIds = $inRound(AiReview::query()->where('status', AiReview::STATUS_DONE))->distinct()->pluck('product_id');
            $openIds = $inRound(AiReview::query()->where('status', AiReview::STATUS_REQUESTED))->distinct()->pluck('product_id');

            // Latest finished review per product drives the averages.
            $latest = $inRound(AiReview::query())
                ->where('status', AiReview::STATUS_DONE)
                ->orderByDesc('reviewed_at')
                ->get(['id', 'product_id', 'score', 'result', 'reviewed_at'])
                ->unique('product_id');

            $aspectAvg = [];
            foreach (array_keys(AiReview::ASPECTS) as $key) {
                $scores = $latest->map(fn ($r) => data_get($r->result, $key.'.score'))->filter(fn ($s) => is_numeric($s));
                $aspectAvg[$key] = $scores->isEmpty() ? null : (int) round($scores->avg());
            }

            $scored = $latest->whereNotNull('score');

            $stats = [
                'total' => $total,
                'reviewed' => $reviewedIds->count(),
                'unreviewed' => max(0, $total - $reviewedIds->count()),
                'requested' => $openIds->count(),
                'avg_score' => $scored->isEmpty() ? null : (int) round($scored->avg('score')),
                'last_7_days' => $inRound(AiReview::query())->where('status', AiReview::STATUS_DONE)->where('reviewed_at', '>=', now()->subDays(7))->count(),
                'aspect_avg' => $aspectAvg,
                'low' => $scored->filter(fn ($r) => $r->score < 50)->count(),
            ];

            $reviews = $inRound(AiReview::query())
                ->with('product:id,name,sku,slug')
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->orderByRaw("case status when 'requested' then 0 when 'done' then 1 else 2 end")
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString();

            $unreviewed = Product::query()
                ->whereNotIn('id', $reviewedIds)
                ->whereNotIn('id', $openIds)
                ->orderBy('id')
                ->limit(20)
                ->get(['id', 'name', 'sku']);
        }

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('ai.reviews.index')
            ->withVariables([
                'ready' => $ready,
                'stats' => $stats,
                'reviews' => $reviews,
                'unreviewed' => $unreviewed,
                'actions' => $actions,
                'status' => $status,
                'aspects' => AiReview::ASPECTS,
                'round' => $round,
            ]);

        return $this->view();
    }

    public function show(AiReview $review)
    {
        $this->title('بررسی AI محصول #'.$review->product_id);

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('ai.reviews.show')
            ->withVariables([
                'review' => $review,
                'product' => Product::query()->find($review->product_id),
                'history' => AiReview::query()
                    ->where('product_id', $review->product_id)
                    ->where('status', AiReview::STATUS_DONE)
                    ->orderByDesc('reviewed_at')
                    ->get(['id', 'score', 'reviewed_at', 'source']),
                'aspects' => AiReview::ASPECTS,
            ]);

        return $this->view();
    }

    /** Everything agents did across the shop, newest first, with simple filters. */
    public function feed(Request $request)
    {
        $this->title('فعالیت AI');

        $ready = Schema::hasTable('shop_ai_activities');
        $type = (string) $request->query('type', '');
        $resource = (string) $request->query('resource', '');
        $round = $this->aiRound($request);
        $rid = $round['id'];
        $roundStats = [];

        $items = collect();
        $names = [];
        $counts = [];
        if ($ready) {
            $inRound = fn ($query) => $rid ? $query->where('round_id', $rid) : $query;
            $counts = $inRound(AiActivity::query())->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type')->all();
            if ($round['rounds']->isNotEmpty()) {
                $perRound = AiActivity::query()->selectRaw('round_id, count(*) as total')->groupBy('round_id')->pluck('total', 'round_id')->all();
                foreach ($round['rounds'] as $r) {
                    $roundStats[$r->id] = (int) ($perRound[$r->id] ?? 0);
                }
            }
            $items = $inRound(AiActivity::query())
                ->when($type !== '', fn ($q) => $q->where('type', $type))
                ->when($resource !== '', fn ($q) => $q->where('resource_type', $resource))
                ->orderByDesc('id')
                ->paginate(40)
                ->withQueryString();

            // One lookup per distinct resource on the page, so every row can name and link what it touched.
            foreach ($items as $row) {
                $rType = $row->resource_type ?: ($row->product_id ? 'product' : null);
                $rId = $row->resource_id ?: $row->product_id;
                if (! $rType || ! $rId || isset($names[$rType.':'.$rId])) {
                    continue;
                }
                $res = \RMS\Shop\Ai\Resources\AiResourceRegistry::get($rType);
                $model = $res?->find((int) $rId);
                $names[$rType.':'.$rId] = $model ? [$res->title($model), $res->adminUrl($model), $res->label()] : null;
            }
        }

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('ai.reviews.feed')
            ->withVariables([
                'ready' => $ready,
                'items' => $items,
                'names' => $names,
                'counts' => $counts,
                'type' => $type,
                'resource' => $resource,
                'types' => AiActivity::TYPES,
                'resources' => \RMS\Shop\Ai\Resources\AiResourceRegistry::all(),
                'round' => $round,
                'roundStats' => $roundStats,
            ]);

        return $this->view();
    }

    /** Archive everything done so far and begin a fresh round (open requests and pending proposals carry over). */
    public function startRound(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:120']]);

        if (! AiRound::available()) {
            return back()->withErrors(['round' => 'جدول دورها هنوز ساخته نشده است. مهاجرت shop را اجرا کنید.']);
        }

        $round = AiRound::startNew($data['name'] ?? null, auth('admin')->id());
        AiActivity::record(null, null, 'note', 'دور تازه شروع شد: '.$round->label(), ['round_id' => $round->id]);

        return redirect()->route('admin.shop.ai-reviews.index')
            ->with('success', $round->label().' شروع شد؛ کارهای دور قبل در آرشیو «فعالیت AI» هستند.');
    }

    /** Full timeline of what the agent did on one product. */
    public function activity(Product $product)
    {
        $this->title('تاریخچهٔ کار AI روی محصول #'.$product->id);

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('ai.reviews.activity')
            ->withVariables([
                'product' => $product,
                'items' => Schema::hasTable('shop_ai_activities')
                    ? AiActivity::query()->where('product_id', $product->id)->orderByDesc('id')->paginate(50)
                    : collect(),
                'types' => AiActivity::TYPES,
            ]);

        return $this->view();
    }

    public function request(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'aspects' => ['nullable', 'array'],
            'aspects.*' => ['string', 'in:'.implode(',', array_keys(AiReview::ASPECTS))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->createRequest($product->id, $data['aspects'] ?? [], $data['note'] ?? null);

        return back()->with('success', 'درخواست بررسی AI برای این محصول ثبت شد. نتیجه در «آمار بررسی AI» می‌آید.');
    }

    public function requestAll(): RedirectResponse
    {
        // Done reviews only count inside the current round; open requests count from any round.
        $rid = AiRound::currentId();
        $taken = AiReview::query()
            ->where(function ($q) use ($rid) {
                $q->where('status', AiReview::STATUS_REQUESTED)
                    ->orWhere(fn ($d) => $d->where('status', AiReview::STATUS_DONE)->when($rid, fn ($x) => $x->where('round_id', $rid)));
            })
            ->select('product_id');
        $count = 0;
        foreach (Product::query()->whereNotIn('id', $taken)->orderBy('id')->cursor() as $product) {
            $this->createRequest($product->id, [], null);
            $count++;
        }

        return redirect()->route('admin.shop.ai-reviews.index')
            ->with('success', $count > 0 ? "درخواست بررسی برای {$count} محصول ثبت شد." : 'همهٔ محصولات بررسی یا در صف هستند.');
    }

    public function dismiss(AiReview $review): RedirectResponse
    {
        if ($review->status === AiReview::STATUS_REQUESTED) {
            $review->forceFill(['status' => AiReview::STATUS_DISMISSED])->save();
        }

        return back()->with('success', 'درخواست لغو شد.');
    }

    protected function createRequest(int $productId, array $aspects, ?string $note): AiReview
    {
        // One open request per product: asking again replaces the earlier one.
        AiReview::query()->where('product_id', $productId)->where('status', AiReview::STATUS_REQUESTED)->delete();

        return AiReview::query()->create([
            'product_id' => $productId,
            'status' => AiReview::STATUS_REQUESTED,
            'aspects' => $aspects !== [] ? array_values($aspects) : array_keys(AiReview::ASPECTS),
            'note' => $note ? trim($note) : null,
            'requested_by' => auth('admin')->id(),
            'requested_at' => now(),
        ]);
    }
}
