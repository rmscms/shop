<?php

namespace RMS\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RMS\Shop\Helpers\AvifHelper;
use RMS\Shop\Jobs\RegenerateAvifForDirectories;
use RMS\Shop\Models\AvifDirectory;

class AvifController extends AdminController
{
    public function table(): string { return 'none'; }
    public function modelName(): string { return 'none'; }

    public function index(Request $request)
    {
        $stats = AvifHelper::getDirectoryStats();
        $routes = [
            'regenerateAll' => route('admin.shop.avif.regenerate-all'),
            'cleanAll' => route('admin.shop.avif.clean-all'),
            'regenerateDirectory' => route('admin.shop.avif.regenerate-directory'),
            'directoryStore' => route('admin.shop.avif.directories.store'),
            'directoryToggle' => route('admin.shop.avif.directories.toggle', ['directory' => '__ID__']),
            'directoryDelete' => route('admin.shop.avif.directories.destroy', ['directory' => '__ID__']),
        ];

        $this->view
            ->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('avif.index')
            ->withVariables(compact('stats', 'routes'));

        return $this->view();
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AvifHelper::getDirectoryStats(),
        ]);
    }

    public function regenerateAll(Request $request): JsonResponse
    {
        try {
            $clean = (bool) $request->boolean('clean_existing');
            RegenerateAvifForDirectories::dispatch(AvifHelper::getTargetDirectories(), $clean);

            return response()->json([
                'success' => true,
                'message' => 'بازسازی AVIF در صف قرار گرفت.',
                'data' => ['queued' => count(AvifHelper::getTargetDirectories())],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function cleanAll(): JsonResponse
    {
        try {
            $result = AvifHelper::cleanAvifFiles();

            return response()->json([
                'success' => true,
                'message' => 'فایل‌های AVIF پاکسازی شدند.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function regenerateDirectory(Request $request): JsonResponse
    {
        $dir = $request->input('directory');
        if (!$dir) {
            return response()->json(['success' => false, 'message' => 'Directory required'], 400);
        }

        $directory = trim($dir, '/');
        if (!in_array($directory, $this->publicDirectories(), true)) {
            return response()->json(['success' => false, 'message' => 'پوشه مجاز نیست.'], 400);
        }

        try {
            RegenerateAvifForDirectories::dispatch([
                ['type' => 'public', 'dir' => $directory],
            ]);

            return response()->json([
                'success' => true,
                'message' => "بازسازی برای {$directory} در صف قرار گرفت.",
                'data' => ['directory' => $directory],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function uploadAndConvert(Request $request)
    {
        // Implement single file upload and conversion logic if needed
        return response()->json(['ok' => true, 'message' => 'Not implemented yet']);
    }

    public function storeDirectory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:public,storage'],
        ]);

        $path = trim($data['path'], '/');
        $type = $data['type'];

        $directory = AvifDirectory::query()->firstOrNew([
            'path' => $path,
            'type' => $type,
        ]);

        $directory->fill([
            'active' => true,
            'is_default' => $directory->is_default ?? false,
        ]);
        $directory->save();

        return response()->json([
            'success' => true,
            'message' => 'پوشه جدید اضافه شد.',
            'data' => $directory,
        ]);
    }

    public function toggleDirectory(AvifDirectory $directory): JsonResponse
    {
        $directory->active = !$directory->active;
        $directory->save();

        return response()->json([
            'success' => true,
            'message' => $directory->active ? 'پوشه فعال شد.' : 'پوشه غیرفعال شد.',
            'data' => $directory,
        ]);
    }

    public function destroyDirectory(AvifDirectory $directory): JsonResponse
    {
        if ($directory->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'حذف پوشه‌های پیش‌فرض مجاز نیست.',
            ], 422);
        }

        $directory->delete();

        return response()->json([
            'success' => true,
            'message' => 'پوشه حذف شد.',
        ]);
    }

    protected function publicDirectories(): array
    {
        return AvifDirectory::query()
            ->where('type', 'public')
            ->where('active', true)
            ->pluck('path')
            ->map(fn ($path) => trim($path, '/'))
            ->unique()
            ->values()
            ->all();
    }
}

