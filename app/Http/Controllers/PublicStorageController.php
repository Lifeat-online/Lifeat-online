<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicStorageController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        abort_if($path === '' || str_contains($path, '..') || str_starts_with($path, '/'), 404);

        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        $stream = $disk->readStream($path);

        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Cache-Control' => 'public, max-age=604800',
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
        ]);
    }
}
