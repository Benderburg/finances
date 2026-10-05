<?php

namespace Noros\Core\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Noros\Core\Models\SystemTranslation;
use Noros\Core\Policies\SystemTranslationPolicy;
use Noros\Core\Support\DatabaseTranslationLoader;
use Noros\Core\Support\TranslationLibrary;

class TranslationLibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TranslationLibrary::class);
        $this->app->extend('translation.loader', function ($loader, $app): DatabaseTranslationLoader {
            $databaseLoader = new DatabaseTranslationLoader($app['files'], array_merge([__DIR__.'/../../lang'], $loader->paths()));
            foreach ($loader->namespaces() as $namespace => $path) {
                $databaseLoader->addNamespace($namespace, $path);
            }
            foreach ($loader->jsonPaths() as $path) {
                $databaseLoader->addJsonPath($path);
            }

            return $databaseLoader;
        });
    }

    public function boot(): void
    {
        $locales = config('noros.locales');
        $default = config('noros.default_locale');
        $fallback = config('noros.fallback_locale');
        if (! is_array($locales) || ! isset($locales[$default], $locales[$fallback])) {
            throw new \LogicException('Noros default and fallback locales must be enabled.');
        }
        $this->app->setLocale($default);
        $this->app->setFallbackLocale($fallback);

        Gate::policy(SystemTranslation::class, SystemTranslationPolicy::class);
    }
}
