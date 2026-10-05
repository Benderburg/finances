<?php

namespace Noros\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        abort_unless(is_string($locale) && array_key_exists($locale, config('noros.locales')), 404);
        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
        $request->attributes->set('noros.locale', $locale);
        $request->route()->forgetParameter('locale');

        return $next($request);
    }
}
