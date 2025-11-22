<?php

namespace RMS\Shop\Support\PanelApi\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use RMS\Shop\Support\PanelApi\PanelApiResponder;

trait HandlesPanelApiResponse
{
    protected function apiSuccess(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        return app(PanelApiResponder::class)->success($data, $meta, $status, request());
    }

    protected function apiError(array $errors, int $status = 400, array $meta = []): JsonResponse
    {
        return app(PanelApiResponder::class)->error($errors, $status, $meta, request());
    }

    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}

