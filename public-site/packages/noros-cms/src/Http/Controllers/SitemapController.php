<?php

namespace Noros\Cms\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Noros\Cms\Models\ContentTranslation;
use Noros\Cms\Models\Page;
use Noros\Cms\Models\PortfolioProject;
use Noros\Cms\Models\Post;
use Noros\Cms\Support\ContentSeo;
use Noros\Core\Support\SiteSeo;

class SitemapController extends Controller
{
    public function __invoke(ContentSeo $content): Response
    {
        abort_unless(app(SiteSeo::class)->settings()['sitemap_enabled'], 404);
        $entries = [];
        if (config('noros-cms.features.blog')) {
            foreach (array_keys(config('noros.locales')) as $locale) {
                $seo = $content->forBlogIndex(locale: $locale, includeQuery: false);
                if ($seo->isIndexable()) {
                    $entries[] = ['url' => route('blog.index', ['locale' => $locale]), 'updated_at' => null];
                }
            }
        }
        foreach (['pages' => Page::class, 'blog' => Post::class, 'portfolio' => PortfolioProject::class] as $feature => $modelClass) {
            if (! config('noros-cms.features.'.$feature)) {
                continue;
            }
            foreach ($modelClass::query()->published()->cursor() as $model) {
                $urls = $content->indexableUrls($model);
                if ($urls === []) {
                    continue;
                }
                $translatedAt = ContentTranslation::query()->where('entity_type', $model->getTable())->where('entity_id', $model->getKey())->max('updated_at');
                $updatedAt = $model->updated_at;
                if ($translatedAt && (! $updatedAt || Carbon::parse($translatedAt)->greaterThan($updatedAt))) {
                    $updatedAt = Carbon::parse($translatedAt);
                }
                foreach ($urls as $url) {
                    $entries[] = ['url' => $url, 'updated_at' => $updatedAt];
                }
            }
        }

        return response()->view('noros-cms::sitemap', compact('entries'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
