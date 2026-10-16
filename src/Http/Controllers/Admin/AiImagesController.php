<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RMS\Shop\Ai\ImageCandidates;
use RMS\Shop\Models\AiImageCandidate;
use RMS\Shop\Models\Product;
use Throwable;

class AiImagesController extends ShopAdminController
{
    public function table(): string
    {
        return 'shop_ai_image_candidates';
    }

    public function modelName(): string
    {
        return AiImageCandidate::class;
    }

    public function index(Request $request)
    {
        $this->title('عکس‌های پیشنهادی محصولات');

        $ready = Schema::hasTable('shop_ai_image_candidates');
        $products = $ready
            ? Product::query()
                ->whereIn('id', AiImageCandidate::query()->where('status', AiImageCandidate::STATUS_CANDIDATE)->whereNotNull('product_id')->select('product_id'))
                ->orderBy('id')
                ->paginate(10)
            : collect();

        $candidates = $ready && $products->isNotEmpty()
            ? AiImageCandidate::query()
                ->whereIn('product_id', $products->pluck('id'))
                ->where('status', AiImageCandidate::STATUS_CANDIDATE)
                ->orderBy('id')
                ->get()
                ->groupBy('product_id')
            : collect();

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('ai.images')
            ->withVariables([
                'ready' => $ready,
                'products' => $products,
                'candidates' => $candidates,
                'waitingForProducts' => $ready
                    ? AiImageCandidate::query()->whereNull('product_id')->where('status', AiImageCandidate::STATUS_CANDIDATE)->count()
                    : 0,
            ]);

        return $this->view();
    }

    /**
     * Download the ticked photos into the product; the rest of its suggestions are dismissed.
     */
    public function apply(Request $request, Product $product, ImageCandidates $images): RedirectResponse
    {
        $picked = array_map('intval', (array) $request->input('candidates', []));
        $open = AiImageCandidate::query()
            ->where('product_id', $product->id)
            ->where('status', AiImageCandidate::STATUS_CANDIDATE)
            ->orderBy('id')
            ->get();

        $attached = 0;
        $errors = [];
        foreach ($open as $candidate) {
            if (!in_array($candidate->id, $picked, true)) {
                $candidate->forceFill(['status' => AiImageCandidate::STATUS_REJECTED])->save();
                continue;
            }

            try {
                $images->attach($candidate);
                $attached++;
            } catch (Throwable $e) {
                // Keep it open so it can be retried or dismissed later.
                $candidate->forceFill(['error' => mb_substr($e->getMessage(), 0, 1000)])->save();
                $errors[] = $e->getMessage();
            }
        }

        $redirect = redirect()->route('admin.shop.ai.images.index', ['page' => $request->input('page')])
            ->with('success', "{$attached} عکس به «{$product->name}» اضافه شد.");

        return $errors === [] ? $redirect : $redirect->withErrors(['images' => implode(' | ', array_unique($errors))]);
    }
}
