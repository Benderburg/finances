<?php

return [
    'title' => 'Cache', 'driver' => 'Driver', 'status' => 'Status', 'host' => 'Host', 'port' => 'Port', 'ping' => 'Ping',
    'connected' => 'Connected', 'disabled' => 'Disabled', 'error' => 'Error',
    'test' => 'Test connection', 'clear' => 'Clear site cache', 'cleared' => 'Site cache invalidated',
    'clear_description' => 'Invalidate cached site data? Sessions, carts and rate limits are preserved.',
    'description' => 'Redis is optional. When it is disabled, the platform uses standard caching. Redis can be enabled on the server to improve performance.',
    'error_description' => 'Redis is unavailable. Standard caching is active and the site remains operational.',
];
