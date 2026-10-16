<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RMS\Shop\Ai\AiManager;
use RMS\Shop\Ai\ProductAiEnricher;
use RMS\Shop\Jobs\EnrichProductWithAi;
use RMS\Shop\Models\AiSuggestion;
use RMS\Shop\Models\Brand;
use RMS\Shop\Models\Category;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductFeature;

class AiSuggestionsController extends ShopAdminController
{
    public function table(): string
    {
        return 'shop_ai_suggestions';
    }

    public function modelName(): string
    {
        return AiSuggestion::class;
    }

    public function index(Request $request, AiManager $ai)
    {
        $this->title('دستیار هوش مصنوعی محصولات');

        $ready = Schema::hasTable('shop_ai_suggestions');
        $status = (string) $request->query('status', '');

        $suggestions = $ready
            ? AiSuggestion::query()
                ->with('product:id,name,sku')
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->orderByRaw("case status when 'ready' then 0 when 'failed' then 1 when 'processing' then 2 when 'pending' then 3 else 4 end")
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString()
            : collect();

        $counts = $ready
            ? AiSuggestion::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all()
            : [];

        $usage = $ready
            ? AiSuggestion::query()->selectRaw('coalesce(sum(input_tokens),0) as input, coalesce(sum(output_tokens),0) as output')->first()
            : null;

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('ai.index')
            ->withVariables([
                'ready' => $ready,
                'configured' => $ai->configured(),
                'driver' => $ai->driverName(),
                'model' => (string) config('shop.ai.drivers.'.$ai->driverName().'.model'),
                'suggestions' => $suggestions,
                'counts' => $counts,
                'status' => $status,
                'usage' => $usage,
                'productsTotal' => $ready ? Product::query()->count() : 0,
                'queueName' => (string) config('shop.ai.queue', 'shop-ai'),
            ]);

        return $this->view();
    }

    public function show(AiSuggestion $suggestion)
    {
        $this->title('پیشنهاد AI برای محصول #'.$suggestion->product_id);

        $product = Product::query()->with(['category', 'brand'])->find($suggestion->product_id);
        $payload = (array) $suggestion->payload;

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('ai.show')
            ->withVariables([
                'suggestion' => $suggestion,
                'product' => $product,
                'payload' => $payload,
                'suggestedCategory' => !empty($payload['category_id']) ? Category::query()->find($payload['category_id']) : null,
                'suggestedBrand' => !empty($payload['brand_id']) ? Brand::query()->find($payload['brand_id']) : null,
                'currentFeatures' => $product
                    ? ProductFeature::query()->where('product_id', $product->id)->orderBy('sort')->get(['name', 'value'])
                    : collect(),
            ]);

        return $this->view();
    }

    public function enqueue(Request $request, ProductAiEnricher $enricher, AiManager $ai): RedirectResponse
    {
        if (!$ai->configured()) {
            return back()->withErrors(['ai' => 'کلید API هوش مصنوعی در .env تنظیم نشده است.']);
        }

        $data = $request->validate([
            'scope' => ['required', 'in:all,missing,product'],
            'product_id' => ['nullable', 'integer', 'required_if:scope,product'],
        ]);

        $query = Product::query()->orderBy('id');
        if ($data['scope'] === 'product') {
            $query->whereKey((int) $data['product_id']);
        } else {
            if ($data['scope'] === 'missing') {
                $query->where(fn ($q) => $q->whereNull('description')->orWhere('description', ''));
            }
            // Bulk runs skip products that already have work in progress or were applied.
            $query->whereNotIn('id', AiSuggestion::query()
                ->whereIn('status', [AiSuggestion::STATUS_PENDING, AiSuggestion::STATUS_PROCESSING, AiSuggestion::STATUS_READY, AiSuggestion::STATUS_APPLIED])
                ->select('product_id'));
        }

        $count = 0;
        foreach ($query->cursor() as $product) {
            EnrichProductWithAi::dispatch($enricher->queue($product)->id);
            $count++;
        }

        return redirect()
            ->route('admin.shop.ai.index')
            ->with('success', $count > 0
                ? "{$count} محصول به صف هوش مصنوعی رفت. نتیجه‌ها کم‌کم در همین صفحه می‌آیند."
                : 'محصولی برای پردازش نماند.');
    }

    public function apply(Request $request, AiSuggestion $suggestion, ProductAiEnricher $enricher): RedirectResponse
    {
        if ($suggestion->status !== AiSuggestion::STATUS_READY) {
            return back()->withErrors(['ai' => 'این پیشنهاد آمادهٔ اعمال نیست.']);
        }

        $data = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string', 'in:'.implode(',', ProductAiEnricher::APPLICABLE_FIELDS)],
        ]);

        $enricher->apply($suggestion, $data['fields']);

        return redirect()
            ->route($request->boolean('next') ? 'admin.shop.ai.next' : 'admin.shop.ai.index')
            ->with('success', 'پیشنهاد روی محصول #'.$suggestion->product_id.' اعمال شد.');
    }

    public function reject(Request $request, AiSuggestion $suggestion): RedirectResponse
    {
        $suggestion->forceFill(['status' => AiSuggestion::STATUS_REJECTED])->save();

        return redirect()
            ->route($request->boolean('next') ? 'admin.shop.ai.next' : 'admin.shop.ai.index')
            ->with('success', 'پیشنهاد رد شد.');
    }

    public function retry(AiSuggestion $suggestion, ProductAiEnricher $enricher): RedirectResponse
    {
        $product = Product::query()->findOrFail($suggestion->product_id);
        EnrichProductWithAi::dispatch($enricher->queue($product)->id);

        return redirect()
            ->route('admin.shop.ai.index')
            ->with('success', 'محصول #'.$product->id.' دوباره به صف رفت.');
    }

    /**
     * Jump to the next suggestion waiting for review.
     */
    public function next(): RedirectResponse
    {
        session()->reflash();
        $next = AiSuggestion::query()->where('status', AiSuggestion::STATUS_READY)->orderBy('id')->first();

        return $next
            ? redirect()->route('admin.shop.ai.show', $next)
            : redirect()->route('admin.shop.ai.index');
    }
}
