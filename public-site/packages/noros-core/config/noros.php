<?php

return [
    'locales' => ['en' => 'English', 'ro' => 'Română', 'ru' => 'Русский'],
    'default_locale' => env('NOROS_DEFAULT_LOCALE', 'en'),
    'fallback_locale' => env('NOROS_FALLBACK_LOCALE', 'en'),
    'bootstrap_admin_email' => env('NOROS_BOOTSTRAP_ADMIN_EMAIL'),
    'permissions' => ['access_admin', 'manage_users', 'manage_roles', 'manage_settings', 'manage_translations', 'manage_media', 'manage_pages', 'manage_blog', 'manage_comments', 'moderate_comments', 'manage_ratings', 'manage_portfolio', 'manage_menus', 'manage_products', 'manage_orders', 'manage_customers'],
];
