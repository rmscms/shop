<?php

namespace RMS\Shop\Contracts\PanelApi\Auth;

use Illuminate\Http\Request;
use RMS\Shop\Support\PanelApi\Auth\PanelAuthResult;

interface PanelAuthDriver
{
    public function attempt(Request $request): PanelAuthResult;
}

