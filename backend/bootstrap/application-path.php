<?php

// Two existing Laravel applications share one document root, never one container
// or database. Keep authenticated routes on Norocel; Noros handles public URLs.
return static function (string $path): bool {
    return (bool) preg_match('~^/(?:app|login|register|forgot-password|reset-password|operations|accounts|goals|savings|budgets|liabilities|reports|categories|csv|settings|more|admin|backup|api|auth|sanctum|up|sw\.js|manifest\.webmanifest|build|icons)(?:/|$)~D', $path);
};
