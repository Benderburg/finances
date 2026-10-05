<?php

namespace Noros\Core\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Noros\Core\Support\CacheService;

class CacheStatus extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected string $view = 'noros-core::filament.pages.cache-status';

    #[Locked]
    public array $cacheStatus = [];

    public static function canAccess(): bool
    {
        return Gate::allows('manage_settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('noros-core::cache.title');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('noros-core::admin.ui_platform');
    }

    public function mount(): void
    {
        $this->testConnection();
    }

    public function testConnection(): void
    {
        Gate::authorize('manage_settings');
        $this->cacheStatus = app(CacheService::class)->status();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')->label(__('noros-core::cache.test'))->action(fn () => $this->testConnection()),
            Action::make('clearCache')->label(__('noros-core::cache.clear'))->requiresConfirmation()
                ->modalDescription(__('noros-core::cache.clear_description'))
                ->action(function (): void {
                    Gate::authorize('manage_settings');
                    app(CacheService::class)->invalidate('settings', 'cms', 'cms.navigation', 'shop', 'shop.catalog', 'engagement');
                    Notification::make()->title(__('noros-core::cache.cleared'))->success()->send();
                }),
        ];
    }
}
