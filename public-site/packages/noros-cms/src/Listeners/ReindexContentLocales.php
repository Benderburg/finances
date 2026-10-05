<?php

namespace Noros\Cms\Listeners;

use Illuminate\Support\Facades\DB;
use Noros\Cms\Models\Category;
use Noros\Cms\Models\ContentTranslation;
use Noros\Cms\Models\Page;
use Noros\Cms\Models\PortfolioProject;
use Noros\Cms\Models\Post;
use Noros\Cms\Models\Tag;
use Noros\Cms\Support\LocalizedContent;
use Noros\Core\Events\PlatformConfigurationChanged;
use Noros\Core\Support\TranslationLibrary;

class ReindexContentLocales
{
    public function handle(PlatformConfigurationChanged $event): void
    {
        DB::transaction(function (): void {
            app(TranslationLibrary::class)->lockForWriting();
            $rows = ContentTranslation::query()->whereIn('locale', array_keys(config('noros.locales')));
            // Release all affected paths together: valid fallback changes can swap URLs.
            // Save history first, then reindex removes redirects occupied by current URLs.
            (clone $rows)->whereNotNull('path')->chunkById(100, function ($translations): void {
                foreach ($translations as $translation) {
                    DB::table('cms_slug_redirects')->updateOrInsert(
                        ['entity_type' => $translation->entity_type, 'locale' => $translation->locale, 'path' => $translation->path],
                        ['entity_id' => $translation->entity_id],
                    );
                }
            });
            $rows->update(['path' => null]);
            $content = app(LocalizedContent::class);
            foreach ([Page::class, Post::class, PortfolioProject::class, Category::class, Tag::class] as $class) {
                $class::query()->chunkById(100, function ($models) use ($content): void {
                    foreach ($models as $model) {
                        $content->reindex($model);
                    }
                });
            }
        });
    }
}
