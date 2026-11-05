<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RMS\Shop\Ai\AgentGuide;
use RMS\Shop\Ai\ProductAiEnricher;
use RMS\Shop\Ai\SlugTask;
use RMS\Shop\Models\AiAction;
use RMS\Shop\Models\AiSuggestion;
use RMS\Shop\Models\Product;

/**
 * Work queue for outside agents. Agents read what needs doing and submit suggestions;
 * nothing is applied until an admin approves it in admin/shop/ai.
 */
class AiTaskController extends BaseController
{
    /** First call for an agent: scope, workflows and rules. */
    public function guide(Request $request): JsonResponse
    {
        $base = url('/'.trim((string) config('shop.admin_api.prefix', 'api/v1/admin/shop'), '/'));

        return response()->json([
            'status' => 'ok',
            'data' => AgentGuide::build($base) + [
                'your_token' => [
                    'name' => $request->attributes->get('shop_api_access')?->name,
                    'permission' => $request->attributes->get('shop_api_access')?->permission,
                ],
            ],
            'errors' => [],
            'meta' => [],
        ]);
    }

    public function tasks(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => [[
                'key' => SlugTask::KEY,
                'title' => 'فارسی کردن اسلاگ محصول',
                'items' => 'GET ai/tasks/'.SlugTask::KEY.'/items',
                'submit' => 'POST ai/suggestions {task, product_id, slug, source?}',
            ], [
                'key' => 'product_review',
                'title' => 'بررسی محصول (سئو، محتوا، تصاویر، قیمت، رقابت)',
                'items' => 'GET ai/reviews/queue (درخواست‌های ادمین) یا GET ai/reviews/unreviewed',
                'submit' => 'POST ai/reviews {product_id, review_id?, summary, score?, result:{seo|content|images|price|competition:{score,findings[],recommendations[]}}, source?}',
            ]],
            'errors' => [],
            'meta' => [],
        ]);
    }

    public function items(Request $request, string $task): JsonResponse
    {
        abort_unless($task === SlugTask::KEY, 404);

        $perPage = min(100, max(1, (int) $request->integer('per_page', 50)));

        $products = Product::query()
            // Non-ASCII slugs are already Persian: bytes differ from characters.
            ->whereRaw('LENGTH(slug) = CHAR_LENGTH(slug)')
            ->whereNotIn('id', AiSuggestion::query()
                ->whereIn('status', [AiSuggestion::STATUS_READY, AiSuggestion::STATUS_APPLIED])
                ->select('product_id'))
            ->with('category:id,name', 'brand:id,name')
            ->orderBy('id')
            ->paginate($perPage, ['id', 'name', 'slug', 'category_id', 'brand_id']);

        return response()->json([
            'status' => 'ok',
            'data' => $products->getCollection()->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'category' => $p->category?->name,
                'brand' => $p->brand?->name,
            ])->values(),
            'errors' => [],
            'meta' => ['total' => $products->total(), 'per_page' => $perPage, 'current_page' => $products->currentPage()],
        ]);
    }

    /**
     * Apply changes straight to a product. Only for read_write tokens, and only
     * with the admin's own words attached; every call is logged with before/after values.
     */
    public function apply(Request $request, ProductAiEnricher $enricher): JsonResponse
    {
        $access = $request->attributes->get('shop_api_access');
        if (! $access || ! $access->canWrite()) {
            return $this->apiError(['permission' => ['این توکن فقط خواندنی است. فقط پیشنهاد ثبت کن (POST ai/suggestions) و به اپراتور بگو.']], 403);
        }

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'operator_instruction' => ['required', 'string', 'min:8', 'max:2000'],
            'source' => ['nullable', 'string', 'max:80'],
            'name' => ['nullable', 'string', 'max:255'],
            'short_desc' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:60000'],
            'slug' => ['nullable', 'string', 'max:300'],
            'meta_title' => ['nullable', 'string', 'max:300'],
            'meta_description' => ['nullable', 'string', 'max:600'],
            'focus_keyword' => ['nullable', 'string', 'max:200'],
            'image_alt' => ['nullable', 'string', 'max:300'],
        ]);

        $product = Product::query()->find($data['product_id']);
        if (! $product) {
            return $this->apiError(['product_id' => ['محصول پیدا نشد.']], 404);
        }

        $payload = [];
        $fields = [];
        foreach (['name', 'short_desc', 'description'] as $f) {
            if (filled($data[$f] ?? null)) {
                $payload[$f] = $data[$f];
                $fields[] = $f;
            }
        }
        if (filled($data['slug'] ?? null)) {
            $slug = SlugTask::clean($data['slug']);
            if ($error = SlugTask::validate($product, $slug)) {
                return $this->apiError(['slug' => [$error]], 422, ['cleaned' => $slug]);
            }
            $payload['slug'] = $slug;
            $fields[] = 'slug';
        }
        $seoKeys = array_values(array_filter(['meta_title', 'meta_description', 'focus_keyword', 'image_alt'], fn ($k) => filled($data[$k] ?? null)));
        foreach ($seoKeys as $k) {
            $payload[$k] = $data[$k];
        }
        if ($seoKeys !== []) {
            $fields[] = 'seo';
        }
        if ($fields === []) {
            return $this->apiError(['fields' => ['حداقل یک فیلد برای اعمال بفرست.']], 422);
        }

        $touched = array_merge(array_diff($fields, ['seo']), $seoKeys);
        $before = [];
        foreach ($touched as $k) {
            $before[$k] = $product->getAttribute($k);
        }

        $suggestion = $enricher->fromData($product, $payload, 'agent-direct: '.($data['source'] ?? 'api'));
        $product = $enricher->apply($suggestion, $fields);

        $after = [];
        foreach ($touched as $k) {
            $after[$k] = $product->getAttribute($k) ?? ($payload[$k] ?? null);
        }

        $action = AiAction::query()->create([
            'product_id' => $product->id,
            'access_id' => $access->id,
            'source' => 'agent: '.($data['source'] ?? 'api'),
            'fields' => $touched,
            'before' => $before,
            'after' => $after,
            'operator_instruction' => trim($data['operator_instruction']),
            'suggestion_id' => $suggestion->id,
        ]);

        return response()->json([
            'status' => 'ok',
            'data' => ['action_id' => $action->id, 'applied' => $touched, 'logged_at' => $action->created_at->toIso8601String()],
            'errors' => [],
            'meta' => [],
        ], 201);
    }

    public function submit(Request $request, ProductAiEnricher $enricher): JsonResponse
    {
        $data = $request->validate([
            'task' => ['required', 'in:'.SlugTask::KEY],
            'product_id' => ['required', 'integer'],
            'slug' => ['required', 'string', 'max:300'],
            'source' => ['nullable', 'string', 'max:80'],
        ]);

        $product = Product::query()->find($data['product_id']);
        if (! $product) {
            return response()->json(['status' => 'error', 'data' => null, 'errors' => ['product_id' => ['محصول پیدا نشد.']], 'meta' => []], 404);
        }

        $slug = SlugTask::clean($data['slug']);
        if ($error = SlugTask::validate($product, $slug)) {
            return response()->json(['status' => 'error', 'data' => null, 'errors' => ['slug' => [$error]], 'meta' => ['cleaned' => $slug]], 422);
        }

        $suggestion = $enricher->fromData($product, ['slug' => $slug, 'task' => SlugTask::KEY], 'agent: '.($data['source'] ?? 'api'));

        return response()->json([
            'status' => 'ok',
            'data' => ['suggestion_id' => $suggestion->id, 'status' => $suggestion->status, 'slug' => $slug],
            'errors' => [],
            'meta' => [],
        ], 201);
    }
}
