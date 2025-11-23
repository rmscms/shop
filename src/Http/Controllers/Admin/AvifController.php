<?php

namespace RMS\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminController;
use Illuminate\Http\Request;
use RMS\Shop\Helpers\AvifHelper;
use RMS\Shop\Jobs\RegenerateAvifForDirectories;
use Illuminate\Support\Facades\Log;

class AvifController extends AdminController
{
    public function table(): string { return 'none'; }
    public function modelName(): string { return 'none'; }

    public function index(Request $request)
    {
        $this->view->setTheme('admin')
            ->setTpl('avif.index')
            ->withVariables([
                'stats' => AvifHelper::getDirectoryStats(),
            ]);
        
        return $this->view();
    }

    public function stats()
    {
        return response()->json([
            'ok' => true,
            'stats' => AvifHelper::getDirectoryStats()
        ]);
    }

    public function regenerateAll()
    {
        try {
            RegenerateAvifForDirectories::dispatch(AvifHelper::getTargetDirectories());
            return response()->json(['ok' => true, 'message' => 'Job dispatched successfully']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function cleanAll()
    {
        try {
            AvifHelper::cleanAvifFiles();
            return response()->json(['ok' => true, 'message' => 'Cleanup completed']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function regenerateDirectory(Request $request)
    {
        $dir = $request->input('directory');
        if (!$dir) return response()->json(['ok' => false, 'error' => 'Directory required'], 400);

        try {
            RegenerateAvifForDirectories::dispatch([$dir]);
            return response()->json(['ok' => true, 'message' => "Job dispatched for $dir"]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function uploadAndConvert(Request $request)
    {
        // Implement single file upload and conversion logic if needed
        return response()->json(['ok' => true, 'message' => 'Not implemented yet']);
    }
}

