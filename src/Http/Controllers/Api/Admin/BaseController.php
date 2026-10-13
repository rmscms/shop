<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Routing\Controller;
use RMS\Shop\Support\AdminApi\Concerns\HandlesAdminApiResponse;

class BaseController extends Controller
{
    use HandlesAdminApiResponse;
}
