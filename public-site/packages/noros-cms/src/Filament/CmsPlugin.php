<?php

namespace Noros\Cms\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

class CmsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'noros-cms';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(in: __DIR__.'/Resources', for: 'Noros\\Cms\\Filament\\Resources');
        $panel->pages([Pages\EngagementSettings::class]);
    }

    public function boot(Panel $panel): void {}
}
