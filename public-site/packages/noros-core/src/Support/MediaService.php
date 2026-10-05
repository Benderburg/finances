<?php

namespace Noros\Core\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Noros\Core\Models\Media;
use RuntimeException;
use Throwable;

class MediaService
{
    public function upload(UploadedFile $file, ?string $title = null, array $translations = []): Media
    {
        Gate::authorize('manage_media');
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf', 'text/plain' => 'txt'];
        Validator::make(compact('file', 'title', 'translations'), [
            'file' => ['required', 'file', 'max:10240', 'mimetypes:'.implode(',', array_keys($extensions))],
            'title' => ['nullable', 'string', 'max:255'],
            'translations' => ['array:'.implode(',', array_keys(config('noros.locales')))],
            'translations.*' => ['nullable', 'string', 'max:1000'],
        ])->validate();
        $mime = $file->getMimeType();
        $filename = Str::uuid().'.'.$extensions[$mime];
        $path = $file->storeAs('media/'.now()->format('Y/m'), $filename, ['disk' => 'public', 'visibility' => 'public']);
        if ($path === false) {
            throw new RuntimeException('Could not store the uploaded media.');
        }

        try {
            return Media::create([
                'title' => $title, 'translations' => $translations, 'disk' => 'public',
                'path' => $path, 'mime_type' => $mime, 'size' => $file->getSize(),
            ]);
        } catch (Throwable $error) {
            Storage::disk('public')->delete($path);
            throw $error;
        }
    }

    public function delete(Media $media): void
    {
        Gate::authorize('manage_media');
        if ($media->disk !== 'public' || ! preg_match('~\Amedia/\d{4}/\d{2}/[a-f0-9-]+\.(?:jpg|png|webp|gif|pdf|txt)\z~', $media->path)) {
            throw new RuntimeException('Refusing to delete a file outside the managed media directory.');
        }
        // Retaining an orphan on a storage failure is safer than deleting a file
        // whose database record could not be removed.
        $media->deleteOrFail();
        if (! Storage::disk('public')->delete($media->path)) {
            throw new RuntimeException('Media record removed, but its file could not be deleted.');
        }
    }
}
