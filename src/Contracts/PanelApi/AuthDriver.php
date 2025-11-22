<?php

namespace RMS\Shop\Contracts\PanelApi;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use RMS\Shop\Support\PanelApi\Auth\PanelAuthResult;

interface AuthDriver
{
    public function handle(Request $request): PanelAuthResult;
}

