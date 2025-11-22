<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Routing\Controller;
use RMS\Shop\Support\PanelApi\Concerns\HandlesPanelApiResponse;

class BaseController extends Controller
{
    use HandlesPanelApiResponse;
}

