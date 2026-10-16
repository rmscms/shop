<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RMS\Shop\Ai\AiManager;
use RMS\Shop\Ai\CatalogImporter;
use RMS\Shop\Ai\CatalogPlanner;
use RMS\Shop\Jobs\PlanCatalogWithAi;
use RMS\Shop\Models\AiCatalogItem;
use RMS\Shop\Models\AiCatalogPlan;
use Throwable;

class AiCatalogController extends ShopAdminController
{
    public function table(): string
    {
        return 'shop_ai_catalog_plans';
    }

    public function modelName(): string
    {
        return AiCatalogPlan::class;
    }

    public function index(Request $request)
    {
        $this->title('طرح کاتالوگ با هوش مصنوعی');

        $ready = Schema::hasTable('shop_ai_catalog_items') && Schema::hasTable('shop_ai_catalog_plans');
        $ai = app(AiManager::class);

        $batches = $ready
            ? AiCatalogItem::query()
                ->select('batch', DB::raw('count(*) as total'), DB::raw("sum(case when status = 'staged' then 1 else 0 end) as staged"), DB::raw('max(updated_at) as updated_at'))
                ->groupBy('batch')
                ->orderByDesc('updated_at')
                ->get()
            : collect();

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('ai.catalog.index')
            ->withVariables([
                'ready' => $ready,
                'configured' => $ai->configured(),
                'batches' => $batches,
                'plans' => $ready ? AiCatalogPlan::query()->orderByDesc('id')->limit(30)->get() : collect(),
                'queueName' => (string) config('shop.ai.queue', 'shop-ai'),
            ]);

        return $this->view();
    }

    public function import(Request $request, CatalogImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $file = $data['file'];
        $batch = CatalogImporter::batchFromPath($file->getClientOriginalName());
        $path = $file->storeAs('shop-catalog', $batch.'.'.strtolower($file->getClientOriginalExtension()));

        try {
            $stats = $importer->import(storage_path('app/'.$path), $batch);
        } catch (Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.shop.ai.catalog.index')
            ->with('success', "فایل «{$batch}» خوانده شد: {$stats['total']} ردیف ({$stats['created']} تازه، {$stats['updated']} به‌روز).");
    }

    public function plan(Request $request, CatalogPlanner $planner, AiManager $ai): RedirectResponse
    {
        if (!$ai->configured()) {
            return back()->withErrors(['ai' => 'کلید API هوش مصنوعی در .env تنظیم نشده است.']);
        }

        $batch = (string) $request->validate(['batch' => ['required', 'string', 'max:100']])['batch'];
        if (!AiCatalogItem::query()->where('batch', $batch)->where('status', AiCatalogItem::STATUS_STAGED)->exists()) {
            return back()->withErrors(['batch' => 'ردیف واردنشده‌ای در این فایل نمانده است.']);
        }

        PlanCatalogWithAi::dispatch($planner->queue($batch)->id);

        return redirect()
            ->route('admin.shop.ai.catalog.index')
            ->with('success', 'ساخت طرح به صف رفت. چند دقیقه بعد همین صفحه را تازه کنید.');
    }

    public function show(AiCatalogPlan $plan)
    {
        $this->title('طرح کاتالوگ #'.$plan->id);

        $payload = (array) $plan->payload;
        $ids = collect($payload['groups'] ?? [])->pluck('items')->flatten(1)->pluck('id')->all();

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('ai.catalog.show')
            ->withVariables([
                'plan' => $plan,
                'payload' => $payload,
                'items' => AiCatalogItem::query()->whereIn('id', $ids)->get()->keyBy('id'),
                'suspicious' => collect($payload['suspicious'] ?? [])->keyBy('id'),
            ]);

        return $this->view();
    }

    public function apply(Request $request, AiCatalogPlan $plan, CatalogPlanner $planner): RedirectResponse
    {
        if ($plan->status !== AiCatalogPlan::STATUS_READY) {
            return back()->withErrors(['plan' => 'این طرح آمادهٔ اعمال نیست.']);
        }

        $data = $request->validate([
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => ['integer'],
            'mode' => ['array'],
            'mode.*' => ['in:'.CatalogPlanner::MODE_VARIANTS.','.CatalogPlanner::MODE_SEPARATE],
        ]);

        $result = $planner->apply($plan, $data['groups'], $data['mode'] ?? []);

        return redirect()
            ->route('admin.shop.ai.catalog.show', $plan)
            ->with('success', count($result['products']).' محصول (غیرفعال و بدون قیمت) ساخته شد، '
                .count($result['categories_created']).' دستهٔ تازه و '.count($result['brands_created']).' برند تازه. '
                .'حالا از صفحهٔ دستیار AI، «محصولات بدون توضیح» را بفرستید تا متن و ویژگی‌ها پر شوند.');
    }

    public function reject(AiCatalogPlan $plan): RedirectResponse
    {
        if ($plan->status !== AiCatalogPlan::STATUS_APPLIED) {
            $plan->forceFill(['status' => AiCatalogPlan::STATUS_REJECTED])->save();
        }

        return redirect()->route('admin.shop.ai.catalog.index')->with('success', 'طرح رد شد.');
    }
}
