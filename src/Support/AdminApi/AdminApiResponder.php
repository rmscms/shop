<?php

namespace RMS\Shop\Support\AdminApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminApiResponder
{
    public function make(
        mixed $data = null,
        array $meta = [],
        array $errors = [],
        string $status = null,
        int $httpStatus = 200,
        ?Request $request = null
    ): JsonResponse {
        $request ??= request();

        $config = config('shop.admin_api.response');

        $payload = [
            $config['status_key'] ?? 'status' => $status ?? $config['default_status'] ?? 'ok',
            $config['data_key'] ?? 'data' => $data,
            $config['errors_key'] ?? 'errors' => $errors,
            $config['meta_key'] ?? 'meta' => $meta,
        ];

        return response()->json($payload, $httpStatus);
    }

    public function success(mixed $data = null, array $meta = [], int $status = 200, ?Request $request = null): JsonResponse
    {
        return $this->make($data, $meta, [], 'ok', $status, $request);
    }

    public function error(array $errors, int $status = 400, array $meta = [], ?Request $request = null): JsonResponse
    {
        return $this->make(null, $meta, $errors, 'error', $status, $request);
    }
}
