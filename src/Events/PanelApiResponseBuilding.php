<?php

namespace RMS\Shop\Events;

use Illuminate\Http\Request;
use RMS\Shop\Support\PanelApi\PanelApiResponsePayload;

class PanelApiResponseBuilding
{
    public function __construct(
        public PanelApiResponsePayload $payload,
        public Request $request
    ) {
    }
}

