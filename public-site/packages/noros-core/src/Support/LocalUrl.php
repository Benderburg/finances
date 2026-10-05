<?php

namespace Noros\Core\Support;

class LocalUrl
{
    public function resolve(?string $path, ?string $locale = null): string
    {
        if (blank($path)) {
            return '#';
        }
        if (preg_match('~^(?:https?://|mailto:|tel:|#)~i', $path)) {
            return $path;
        }
        if (str_contains($path, ':') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return '#';
        }
        $locale ??= app()->getLocale();
        $path = ltrim($path, '/');
        $first = explode('/', $path)[0];
        if (isset(config('noros.locales')[$first]) || in_array($first, ['admin', 'storage', 'img'], true)) {
            return url('/'.$path);
        }

        return url('/'.$locale.($path !== '' ? '/'.$path : ''));
    }
}
