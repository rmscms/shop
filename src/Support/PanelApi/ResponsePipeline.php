<?php

namespace RMS\Shop\Support\PanelApi;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use RMS\Shop\Contracts\PanelApi\ResponseModifier;

class ResponsePipeline
{
    public function __construct(protected Container $container)
    {
    }

    public function apply(PanelApiResponsePayload $payload, Request $request): PanelApiResponsePayload
    {
        $modifiers = config('shop.panel_api.response.modifiers', []);

        foreach ($modifiers as $modifierClass) {
            if (!class_exists($modifierClass)) {
                continue;
            }

            $modifier = $this->container->make($modifierClass);

            if ($modifier instanceof ResponseModifier) {
                $modifier->modify($payload, $request);
            }
        }

        return $payload;
    }
}

