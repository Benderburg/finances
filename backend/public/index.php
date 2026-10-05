<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

$financePath = require __DIR__.'/../bootstrap/application-path.php';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$siteEntry = __DIR__.'/../../public-site/public/index.php';
if (! $financePath($path) && is_file($siteEntry) && is_file(__DIR__.'/../../public-site/vendor/autoload.php')) {
    require $siteEntry;
    return;
}

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
