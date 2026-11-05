<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RMS\Shop\Models\AiReview;
use RMS\Shop\Models\Product;

/**
 * Review work queue for outside agents: read what admins asked for, log the result.
 */
class AiReviewController extends BaseController
{
    /** Requests waiting for an agent. */
    public function queue(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $rows = AiReview::query()
            ->with('product:id,name,slug,sku')
            ->where('status', AiReview::STATUS_REQUESTED)
            ->orderBy('id')
            ->paginate($perPage);

        return $this->payload($rows->getCollection()->map(fn (AiReview $r) => [
            'review_id' => $r->id,
            'product_id' => $r->product_id,
            'product' => $r->product?->name,
            'slug' => $r->product?->slug,
            'aspects' => $r->aspects,
            'note' => $r->note,
            'requested_at' => $r->requested_at?->toIso8601String(),
        ])->values(), ['total' => $rows->total(), 'aspects_allowed' => array_keys(AiReview::ASPECTS)]);
    }

    /** Products that never got a finished review (proactive coverage work). */
    public function unreviewed(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 50)));

        $rows = Product::query()
            ->whereNotIn('id', AiReview::query()->where('status', AiReview::STATUS_DONE)->select('product_id'))
            ->orderBy('id')
            ->paginate($perPage, ['id', 'name', 'slug', 'sku']);

        return $this->payload($rows->getCollection()->values(), ['total' => $rows->total()]);
    }

    /**
     * Log a finished review. With review_id it answers a request; without, it records a proactive one.
     * result is keyed by aspect: {"seo": {"score": 0-100, "findings": [...], "recommendations": [...]}}.
     */
    public function submit(Request $request): JsonResponse
    {
        $keys = array_keys(AiReview::ASPECTS);
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'review_id' => ['nullable', 'integer'],
            'summary' => ['required', 'string', 'max:5000'],
            'score' => ['nullable', 'integer', 'between:0,100'],
            'source' => ['nullable', 'string', 'max:80'],
            'result' => ['required', 'array', 'min:1'],
            'result.*.score' => ['nullable', 'integer', 'between:0,100'],
            'result.*.findings' => ['nullable', 'array'],
            'result.*.findings.*' => ['string', 'max:1000'],
            'result.*.recommendations' => ['nullable', 'array'],
            'result.*.recommendations.*' => ['string', 'max:1000'],
        ]);

        $result = array_intersect_key($data['result'], array_flip($keys));
        if ($result === []) {
            return $this->apiError(['result' => ['حداقل یکی از این کلیدها لازم است: '.implode(', ', $keys)]], 422);
        }

        $score = $data['score'] ?? null;
        if ($score === null) {
            $scores = array_filter(array_map(fn ($a) => $a['score'] ?? null, $result), 'is_numeric');
            $score = $scores ? (int) round(array_sum($scores) / count($scores)) : null;
        }

        $review = null;
        if (! empty($data['review_id'])) {
            $review = AiReview::query()
                ->whereKey($data['review_id'])
                ->where('product_id', $data['product_id'])
                ->where('status', AiReview::STATUS_REQUESTED)
                ->first();
            if (! $review) {
                return $this->apiError(['review_id' => ['درخواست باز با این شناسه برای این محصول نیست.']], 404);
            }
        }
        $review ??= new AiReview(['product_id' => $data['product_id'], 'requested_at' => now()]);

        $review->fill([
            'status' => AiReview::STATUS_DONE,
            'aspects' => array_keys($result),
            'result' => $result,
            'summary' => trim($data['summary']),
            'score' => $score,
            'source' => 'agent: '.($data['source'] ?? 'api'),
            'reviewed_at' => now(),
        ])->save();

        return $this->payload(['review_id' => $review->id, 'score' => $score, 'reviewed_at' => $review->reviewed_at->toIso8601String()], [], 201);
    }

    protected function payload(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => $data, 'errors' => [], 'meta' => $meta], $status);
    }
}
