<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Noros\Core\Http\Middleware\LoadPlatformConfiguration;
use Symfony\Component\HttpFoundation\Response;

/** Adapt the native Noros admin locale to this site's /cms panel. */
class CmsConfiguration extends LoadPlatformConfiguration
{
    public function handle(Request $request, Closure $next): Response
    {
        return parent::handle($request, function (Request $request) use ($next): Response {
            $admin = $request->is('cms', 'cms/*', 'livewire/*', 'livewire-*/*');
            if ($admin && $request->hasSession()) {
                $locale = $request->query('locale', $request->session()->get('noros.admin_locale'));
                if (is_string($locale) && isset(config('noros.locales')[$locale])) {
                    $request->session()->put('noros.admin_locale', $locale);
                    app()->setLocale($locale);
                }
            }
            $response = $next($request);
            if ($admin) {
                $response->headers->set('Cache-Control', 'private, no-store');
            }
            return $response;
        });
    }
}
