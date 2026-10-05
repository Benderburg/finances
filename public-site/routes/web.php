<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('home', ['locale' => config('noros.default_locale')], 302));
foreach (['features', 'about', 'privacy', 'terms', 'blog'] as $path) {
    Route::get('/'.$path.'/{slug?}', function (?string $slug = null) use ($path) {
        $route = $path === 'blog' ? ($slug ? 'blog.show' : 'blog.index') : 'pages.show';
        abort_if($path !== 'blog' && $slug !== null, 404);
        return redirect()->route($route, array_filter(['locale' => config('noros.default_locale'), 'path' => $path === 'blog' ? null : $path, 'slug' => $slug]), 302);
    });
}
