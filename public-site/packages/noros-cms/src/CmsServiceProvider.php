<?php

namespace Noros\Cms;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Noros\Cms\Console\BackfillContent;
use Noros\Cms\Contracts\EngagementChallenge;
use Noros\Cms\Events\CommentSubmitted;
use Noros\Cms\Listeners\NotifyCommentModerators;
use Noros\Cms\Listeners\ReindexContentLocales;
use Noros\Cms\Models\Category;
use Noros\Cms\Models\ContentTranslation;
use Noros\Cms\Models\Menu;
use Noros\Cms\Models\MenuItem;
use Noros\Cms\Models\Page;
use Noros\Cms\Models\PortfolioProject;
use Noros\Cms\Models\PortfolioWidget;
use Noros\Cms\Models\Post;
use Noros\Cms\Models\PricingWidget;
use Noros\Cms\Models\Tag;
use Noros\Cms\Support\BlockRegistry;
use Noros\Cms\Support\EngagementSettings;
use Noros\Cms\Support\LocalizedContent;
use Noros\Cms\Support\StandardBlocks;
use Noros\Core\Events\PlatformConfigurationChanged;
use Noros\Core\Models\Setting;
use Noros\Core\Support\CacheService;

class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/noros-cms.php', 'noros-cms');
        $this->app->singleton(BlockRegistry::class);
        $this->app->scoped(LocalizedContent::class);
        $this->app->bind(EngagementChallenge::class, config('noros-cms.engagement.challenge'));
    }

    public function boot(): void
    {
        Setting::saving(function ($setting): void {
            if ($setting->key === 'engagement') {
                if (! is_array($setting->value)) {
                    throw ValidationException::withMessages(['value' => __('noros-cms::engagement.invalid_settings')]);
                }
                $setting->value = app(EngagementSettings::class)->validate($setting->value);
            }
        });
        foreach ([Page::class, Post::class, Category::class, Tag::class, Menu::class, MenuItem::class, ContentTranslation::class, PortfolioProject::class, PortfolioWidget::class, PricingWidget::class] as $model) {
            $model::saved(fn () => app(CacheService::class)->invalidate('cms'));
            $model::deleted(fn () => app(CacheService::class)->invalidate('cms'));
        }
        Relation::morphMap(config('noros-cms.engagement.models', []));
        Event::listen(CommentSubmitted::class, NotifyCommentModerators::class);
        Event::listen(PlatformConfigurationChanged::class, ReindexContentLocales::class);
        $this->commands([BackfillContent::class]);
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'noros-cms');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'noros-cms');
        $this->publishes([__DIR__.'/../dist' => public_path('vendor/noros-cms')], 'noros-cms-assets');
        $registry = $this->app->make(BlockRegistry::class);
        foreach (['hero_primary', 'hero_secondary', 'hero_slider', 'template', 'portfolio_widget', 'pricing_widget', 'portfolio_project', 'content', 'html'] as $type) {
            $registry->register($type, 'noros-cms::blocks.'.$type);
        }
        app(StandardBlocks::class)->register($registry);
    }
}
