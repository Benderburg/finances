<?php

namespace App\Providers;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Noros\Core\Filament\CorePlugin;
use Noros\Cms\Filament\CmsPlugin;
use Noros\Core\Filament\Pages\Auth\Login;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->default()->id('cms')->path('cms')->login(Login::class)
            ->brandName(config('app.name'))
            ->brandLogo(asset('icons/logo.svg'))
            ->darkModeBrandLogo(asset('icons/logo-light.svg'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('icons/favicon.svg').'?v=logo-1')
            ->plugins([new CorePlugin, new CmsPlugin])
            ->pages([Dashboard::class])->widgets([AccountWidget::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                \App\Http\Middleware\SiteCsrf::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])->authMiddleware([Authenticate::class]);
    }
}
