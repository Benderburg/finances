<?php

return [
    'locales' => ['en' => 'English', 'ro' => 'Română', 'ru' => 'Русский'],
    'default_locale' => env('NOROS_DEFAULT_LOCALE', 'ru'),
    'fallback_locale' => env('NOROS_FALLBACK_LOCALE', 'ru'),
    'bootstrap_admin_email' => null,
];
