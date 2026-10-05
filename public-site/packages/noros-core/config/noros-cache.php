<?php

return [
    'redis_enabled' => env('NOROS_REDIS_ENABLED', false),
    'fallback_store' => env('NOROS_CACHE_FALLBACK', 'file'),
    'ttl' => (int) env('NOROS_CACHE_TTL', 300),
    'ttls' => [
        'settings' => (int) env('NOROS_CACHE_SETTINGS_TTL', 600),
        'cms' => (int) env('NOROS_CACHE_CMS_TTL', 300),
        'shop' => (int) env('NOROS_CACHE_SHOP_TTL', 300),
        'engagement' => (int) env('NOROS_CACHE_ENGAGEMENT_TTL', 120),
    ],
    'upload_max_kb' => 10240,
];
