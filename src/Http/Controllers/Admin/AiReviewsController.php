<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RMS\Shop\Models\AiAction;
use RMS\Shop\Models\AiActivity;
use RMS\Shop\Models\AiReview;
use RMS\Shop\Models\Product;

/**
 * Product reviews done by an outside agent: admins request them here and read the logged result.
 */
class AiReviewsController extends ShopAdminController
{
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

        if ($ready) {
            if (Schema::hasTable('shop_ai_actions')) {
                $actions = AiAction::query()->with('product:id,name')->orderByDesc('id')->limit(15)->get();
            }

            $total = Product::query()->count();
            $reviewedIds = AiReview::query()->where('status', AiReview::STATUS_DONE)->distinct()->pluck('product_id');
            $openIds = AiReview::query()->where('status', AiReview::STATUS_REQUESTED)->distinct()->pluck('product_id');

            // Latest finished review per product drives the averages.
            $latest = AiReview::query()
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
                'last_7_days' => AiReview::query()->where('status', AiReview::STATUS_DONE)->where('reviewed_at', '>=', now()->subDays(7))->count(),
                'aspect_avg' => $aspectAvg,
                'low' => $scored->filter(fn ($r) => $r->score < 50)->count(),
            ];

            $reviews = AiReview::query()
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
        $taken = AiReview::query()->whereIn('status', [AiReview::STATUS_DONE, AiReview::STATUS_REQUESTED])->select('product_id');
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
