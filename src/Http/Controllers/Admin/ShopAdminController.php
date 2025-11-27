<?php

namespace RMS\Shop\Http\Controllers\Admin;

use RMS\Core\Controllers\Admin\ProjectAdminController;

abstract class ShopAdminController extends ProjectAdminController
{
    // Base controller for all Shop admin controllers
    // Extend this class instead of ProjectAdminController directly
    // This allows centralized changes to all shop controllers
}

