<?php

namespace Noros\Core\Support;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    public function resolve(?string $path, string $disk = 'public'): ?string
    {
        if (blank($path)) {
            return null;
        }
        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }
        if (str_contains($path, '..') || str_contains($path, ':') || str_starts_with($path, '//')) {
            return null;
        }

        return str_starts_with(ltrim($path, '/'), 'img/') ? asset(ltrim($path, '/')) : Storage::disk($disk)->url(ltrim($path, '/'));
    }
}
