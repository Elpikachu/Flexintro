<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function index(): Response
    {
        $prefix = trim(config('demo.files_prefix'), '/');

        $error = null;
        $files = [];

        try {
            $disk = Storage::disk(config('demo.disk'));

            foreach ($disk->allFiles($prefix) as $path) {
                $files[] = [
                    'path' => $path,
                    'name' => basename($path),
                    'folder' => trim(dirname(substr($path, strlen($prefix))), '/.'),
                    'size' => $disk->size($path),
                    'last_modified' => $disk->lastModified($path),
                ];
            }
        } catch (\Throwable $e) {
            // Object storage not enabled yet, or unreachable: say so instead of
            // showing a stack trace in front of the customer.
            $error = $e->getMessage();
        }

        usort($files, fn ($a, $b) => strcmp($a['path'], $b['path']));

        return response()->view('files', [
            'files' => $files,
            'error' => $error,
            'storage' => [
                'host' => config('demo.object_storage.host'),
                'port' => config('demo.object_storage.port'),
                'bucket' => config('demo.object_storage.bucket'),
            ],
        ]);
    }

    /**
     * Streams the object through the app. There is no public URL to redirect
     * to: the bucket endpoint is reachable inside the project only.
     */
    public function show(string $path): StreamedResponse
    {
        $prefix = trim(config('demo.files_prefix'), '/');
        $path = ltrim($path, '/');

        abort_unless(str_starts_with($path, $prefix.'/'), 404);
        abort_if(str_contains($path, '..'), 404);

        $disk = Storage::disk(config('demo.disk'));

        try {
            abort_unless($disk->exists($path), 404);

            return $disk->response($path, basename($path), [
                'Cache-Control' => 'private, max-age=60',
            ]);
        } catch (FileNotFoundException) {
            abort(404);
        }
    }
}
