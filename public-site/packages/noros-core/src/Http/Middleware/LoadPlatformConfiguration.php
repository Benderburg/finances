<?php

namespace Noros\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Noros\Core\Models\Setting;
use Noros\Core\Support\PlatformConfiguration;
use Symfony\Component\HttpFoundation\Response;

class LoadPlatformConfiguration
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Schema::hasTable('settings')) {
            $platform = Setting::query()->where('key', 'platform')->first();
            if ($platform) {
                app(PlatformConfiguration::class)->apply($platform->value);
            }
        }
        if ($request->is('admin', 'admin/*') && $request->hasSession()) {
            $locale = $request->query('locale', $request->session()->get('noros.admin_locale'));
            if (is_string($locale) && isset(config('noros.locales')[$locale])) {
                $request->session()->put('noros.admin_locale', $locale);
                app()->setLocale($locale);
            }
        }

        return $next($request);
    }
}
