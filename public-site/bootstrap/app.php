<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/site-health')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(replace: [\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class => \App\Http\Middleware\SiteCsrf::class]);
        $middleware->appendToPriorityList(\Illuminate\Session\Middleware\StartSession::class, \Noros\Core\Http\Middleware\LoadPlatformConfiguration::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
