<?php

namespace RMS\Shop\Contracts\PanelApi;

use Illuminate\Http\Request;
use RMS\Shop\Support\PanelApi\PanelApiResponsePayload;

interface ResponseModifier
{
    public function modify(PanelApiResponsePayload $payload, Request $request): void;
}

