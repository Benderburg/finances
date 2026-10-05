<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Noros\Cms\Support\BlockRegistry;
use Noros\Cms\Support\SiteNavigation;
use Noros\Core\Support\Settings;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;

class SiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\Noros\Core\Http\Middleware\LoadPlatformConfiguration::class, \App\Http\Middleware\CmsConfiguration::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');
        }
        $registry = app(BlockRegistry::class);
        foreach (['hero_primary', 'text_image', 'advantages', 'cta', 'content', 'latest_posts'] as $type) {
            $registry->register($type, 'blocks.'.$type, fn () => $this->blockSchema($type));
        }
        View::composer('layouts.site', function ($view): void {
            $settings = app(Settings::class);
            $site = [];
            foreach (config('site') as $key => $default) {
                $site[$key] = $settings->get('site.'.$key, $default);
            }
            foreach (['login_url', 'register_url', 'app_url', 'platform_url', 'social_image'] as $key) {
                $value = $site[$key];
                $site[$key] = is_string($value) && preg_match('~^(?:https?://|/(?!/))[^\s\\\\]*$~', $value) ? $value : config('site.'.$key);
            }
            $view->with('site', $site)->with('menus', app(SiteNavigation::class)->menus());
        });
    }

    private function blockSchema(string $type): array
    {
        $fields = [Toggle::make('enabled')->default(true)];
        if ($type === 'content') {
            return [...$fields, Textarea::make('content')->rows(12)->required(), Select::make('class')->options(['brand-story' => 'Brand story', '' => 'Article'])];
        }
        $fields = [...$fields, TextInput::make('eyebrow')->maxLength(150), TextInput::make('title')->maxLength(255)->required(), Textarea::make('description')->rows(4)->maxLength(2000)];
        if ($type === 'text_image') {
            $fields = [...$fields, Select::make('image')->options(['expenses' => 'Expenses', 'currencies' => 'Currencies', 'goals' => 'Goals', 'steps' => 'How it works'])->required(), Select::make('image_position')->options(['left' => 'Left', 'right' => 'Right']), TextInput::make('anchor'), Repeater::make('items')->schema([TextInput::make('text')->required()])];
        }
        if ($type === 'advantages') {
            $fields[] = Repeater::make('items')->schema([TextInput::make('title')->required(), Textarea::make('description')->required(), Select::make('icon')->options(['wallet' => 'Wallet', 'arrows' => 'Transfers', 'grid' => 'Categories', 'savings' => 'Savings', 'target' => 'Goals', 'currency' => 'Currencies', 'debt' => 'Debts', 'chart' => 'Reports'])->required()]);
        }
        if ($type === 'latest_posts') {
            $fields[] = TextInput::make('limit')->integer()->minValue(1)->maxValue(12)->default(3);
        }
        return $fields;
    }
}
