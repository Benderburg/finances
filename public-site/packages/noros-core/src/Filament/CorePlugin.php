<?php

namespace Noros\Core\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Noros\Core\Http\Middleware\LoadPlatformConfiguration;

class CorePlugin implements Plugin
{
    public function getId(): string
    {
        return 'noros-core';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(in: __DIR__.'/Resources', for: 'Noros\\Core\\Filament\\Resources');
        $panel->pages([Pages\SiteSeoSettings::class, Pages\CacheStatus::class]);
        $panel->middleware([LoadPlatformConfiguration::class]);
        $panel->renderHook('panels::topbar.end', fn () => view('noros-core::admin-locales'));
    }

    public function boot(Panel $panel): void {}
}
