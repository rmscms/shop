<?php

namespace RMS\Shop\Support\PanelApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RMS\Shop\Events\PanelApiResponseBuilding;
use RMS\Shop\Events\PanelApiResponseReady;

class PanelApiResponder
{
    public function __construct(protected ResponsePipeline $pipeline)
    {
    }

    public function make(
        mixed $data = null,
        array $meta = [],
        array $errors = [],
        string $status = null,
        int $httpStatus = 200,
        ?Request $request = null
    ): JsonResponse {
        $request ??= request();

        $config = config('shop.panel_api.response');

        $payload = new PanelApiResponsePayload([
            $config['status_key'] ?? 'status' => $status ?? $config['default_status'] ?? 'ok',
            $config['data_key'] ?? 'data' => $data,
            $config['errors_key'] ?? 'errors' => $errors,
            $config['meta_key'] ?? 'meta' => $meta,
        ]);

        event(new PanelApiResponseBuilding($payload, $request));

        $this->pipeline->apply($payload, $request);

        event(new PanelApiResponseReady($payload, $request));

        return response()->json($payload->all(), $httpStatus);
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

