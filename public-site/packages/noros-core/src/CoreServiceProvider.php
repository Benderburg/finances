<?php

namespace Noros\Core;

use Filament\Forms\Components\FileUpload;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Noros\Core\Console\CreateAdmin;
use Noros\Core\Http\Middleware\LoadPlatformConfiguration;
use Noros\Core\Http\Middleware\SecurityHeaders;
use Noros\Core\Models\User;
use Noros\Core\Providers\TranslationLibraryServiceProvider;
use Noros\Core\Support\CacheConnectionState;
use Noros\Core\Support\CacheService;
use Noros\Core\Support\FileUploadSecurity;
use Noros\Core\Support\ResilientCacheStore;
use Noros\Core\Support\Settings;
use Noros\Core\Support\SiteSeo;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/noros.php', 'noros');
        $this->mergeConfigFrom(__DIR__.'/../config/noros-cache.php', 'noros-cache');
        if (! is_array(config('noros.supported_locales'))) {
            config(['noros.supported_locales' => config('noros.locales')]);
        }
        $this->app->register(TranslationLibraryServiceProvider::class);
        $this->app->scoped(Settings::class);
        $this->app->scoped(SiteSeo::class);
        $this->app->scoped(CacheService::class);
        $this->app->scoped(CacheConnectionState::class);

        $fallback = config('noros-cache.fallback_store', 'file');
        if (! in_array($fallback, ['file', 'database'], true)) {
            $fallback = 'file';
        }
        config(['noros-cache.fallback_store' => $fallback]);
        if (config('noros-cache.redis_enabled') || config('cache.default') === 'redis') {
            config([
                'cache.default' => 'noros_failover',
                'cache.stores.noros_failover' => ['driver' => 'noros-failover', 'stores' => ['redis', $fallback]],
                // Rate limiting must stay on one durable store across Redis outages.
                'cache.limiter' => $fallback,
            ]);
            $connection = config('cache.stores.redis.connection', 'cache');
            foreach (['timeout' => 1.0, 'read_timeout' => 1.0, 'max_retries' => 0] as $option => $value) {
                if (config('database.redis.'.$connection.'.'.$option) === null) {
                    config(['database.redis.'.$connection.'.'.$option => $value]);
                }
            }
        }
        $this->app->resolving('cache', function ($cache): void {
            $cache->extend('noros-failover', fn ($app, array $config) => $cache->repository(
                new ResilientCacheStore($cache, $app['events'], $config['stores']),
            ));
        });
    }

    public function boot(): void
    {
        $this->callAfterResolving(HttpKernel::class, function (Kernel $kernel): void {
            $kernel->prependMiddleware(SecurityHeaders::class);
            $kernel->appendMiddlewareToGroup('web', LoadPlatformConfiguration::class);
        });
        FileUpload::configureUsing(fn (FileUpload $component) => app(FileUploadSecurity::class)->configure($component));
        $this->commands([CreateAdmin::class]);
        URL::defaults(['locale' => config('noros.default_locale')]);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'noros-core');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'noros-core');
        foreach (config('noros.permissions', []) as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }
        $this->publishes([__DIR__.'/../config/noros.php' => config_path('noros.php')], 'noros-config');
        $this->publishes([__DIR__.'/../config/noros-cache.php' => config_path('noros-cache.php')], 'noros-config');
    }
}
