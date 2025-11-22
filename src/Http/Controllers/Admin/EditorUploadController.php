<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Routing\Controller as BaseController;

class EditorUploadController extends BaseController
{
    public function upload(Request $request)
    {
        // CSRF + Auth are enforced by route group middleware
        $request->validate([
            'upload' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/avif',
                'max:4096', // KB => 4MB
            ],
            'context' => ['nullable','string','max:50']
        ]);

        $file = $request->file('upload');
        if (!$file->isValid()) {
            return response()->json(['error' => ['message' => 'Invalid upload']], 422);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $uuid = (string) Str::uuid();
        $dir = 'uploads/ckeditor/'.date('Y/m');
        $origRel = $dir.'/orig/'.$uuid.'.'.$ext;

        // Store original safely under public disk
        $stream = fopen($file->getRealPath(), 'r');
        Storage::disk('public')->put($origRel, $stream);
        if (is_resource($stream)) fclose($stream);

        // Re-encode and strip metadata to WEBP (best-effort)
        $webpRel = $dir.'/webp/'.$uuid.'.webp';
        try {
            if (class_exists(\Intervention\Image\ImageManager::class)) {
                $manager = \Intervention\Image\ImageManager::gd();
                $img = $manager->read($file->getRealPath());
                // Normalize orientation for JPEGs
                try { if (method_exists($img, 'orientate')) { $img->orientate(); } } catch (\Throwable $e) {}
                @mkdir(dirname(Storage::disk('public')->path($webpRel)), 0777, true);
                $img->save(Storage::disk('public')->path($webpRel), 82, 'webp');
            }
        } catch (\Throwable $e) {
            // ignore; fallback to original only
        }

        $url = Storage::disk('public')->url($origRel);
        $urls = ['default' => $url];
        if (Storage::disk('public')->exists($webpRel)) {
            $urls['webp'] = Storage::disk('public')->url($webpRel);
        }

        return response()->json([
            'ok' => true,
            'url' => $url,
            'urls' => $urls,
        ]);
    }
}

