<?php

namespace Noros\Cms\Support;

use Noros\Cms\Models\Page;
use Noros\Cms\Models\PortfolioProject;
use Noros\Cms\Models\Post;
use Noros\Core\Support\MediaUrl;
use Noros\Core\Support\Seo;
use Noros\Core\Support\SiteSeo;

class ContentSeo
{
    /** @return array<string, string> */
    public function urlsForModel(Page|Post|PortfolioProject $model): array
    {
        $content = app(LocalizedContent::class);
        $urls = [];
        foreach (array_keys(config('noros.locales')) as $locale) {
            $path = $content->path($model, $locale);
            $urls[$locale] = match (true) {
                $model instanceof Page => $model->is_home ? route('home', ['locale' => $locale]) : route('pages.show', ['locale' => $locale, 'path' => $path]),
                $model instanceof Post => route('blog.show', ['locale' => $locale, 'slug' => $path]),
                default => route('portfolio.case', ['locale' => $locale, 'case' => $path]),
            };
        }

        return $urls;
    }

    public function hasTranslation(Page|Post|PortfolioProject $model, string $locale): bool
    {
        if ($locale === config('noros.fallback_locale')) {
            return true;
        }
        if (blank($model->translated('title', $locale, false))) {
            return false;
        }
        $body = $model instanceof Page ? 'blocks' : 'content';
        $source = $model->translated($body, config('noros.fallback_locale'));
        if (blank($source) || filled($model->translated($body, $locale, false))) {
            return true;
        }
        if ($model instanceof Page && is_array($source)) {
            foreach ($source as $block) {
                $data = $block['data'] ?? [];
                $translation = $data['translations'][$locale] ?? [];
                if (($data['enabled'] ?? true) && ($translation['enabled'] ?? true) && ! $this->hasTranslatedBlockData($data, $translation)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private function hasTranslatedBlockData(array $source, mixed $translated): bool
    {
        if (! is_array($translated)) {
            return false;
        }
        foreach ($source as $key => $value) {
            if (in_array($key, ['translations', 'enabled', 'id', 'type', 'icon', 'url', 'href', 'src', 'image', 'background', 'anchor', 'spacing', 'alignment', 'text_align', 'image_position', 'class', 'classes', 'limit', 'target'], true)
                || (is_string($key) && preg_match('/(?:_(?:url|href|src|image|path|slug|id|classes|class|icon|target|color|size|width|height|position|alignment|align|style|spacing)\z|\A(?:icon|padding|margin)_)/', $key))) {
                continue;
            }
            if (is_array($value) && ! $this->hasTranslatedBlockData($value, $translated[$key] ?? [])) {
                return false;
            }
            if (is_string($value) && filled($value) && (! is_string($translated[$key] ?? null) || blank($translated[$key]))) {
                return false;
            }
        }

        return true;
    }

    public function indexableUrls(Page|Post|PortfolioProject $model): array
    {
        $urls = [];
        foreach ($this->urlsForModel($model) as $locale => $url) {
            $seo = $this->forModel($model, $locale, false);
            if ($seo->isIndexable() && $seo->canonical === $url) {
                $urls[$locale] = $url;
            }
        }

        return $urls;
    }

    public function forModel(Page|Post|PortfolioProject $model, ?string $locale = null, bool $includeAlternates = true): Seo
    {
        $locale ??= app()->getLocale();
        $site = app(SiteSeo::class);
        $settings = $site->settings($locale);
        $urls = $this->urlsForModel($model);
        $value = fn (string $field): mixed => $model->translated($field, $locale);
        $canonical = $value('canonical_url') ?: $urls[$locale];
        if (! $site->isHttpUrl($canonical)) {
            $canonical = $urls[$locale];
        }
        $robots = $value('seo_robots') ?: $settings['robots'];
        if (! (new Seo(title: '', robots: $settings['robots']))->isIndexable()) {
            $robots = $settings['robots'];
        }
        if ($settings['require_translation'] && ! $this->hasTranslation($model, $locale)) {
            $canonical = $urls[config('noros.fallback_locale')];
            $robots = 'noindex, follow';
        }
        $alternates = $includeAlternates ? $this->indexableUrls($model) : [];
        $default = $settings['x_default_locale'];
        if ($alternates !== []) {
            $alternates['x-default'] = $alternates[$default] ?? $alternates[config('noros.fallback_locale')] ?? reset($alternates);
        }
        $imagePath = $value('og_image') ?: ($model instanceof Post ? $model->getAttribute('cover_image') : ($model instanceof PortfolioProject ? $model->getAttribute('image') : null));
        $image = app(MediaUrl::class)->resolve($imagePath ?: $settings['default_image']);
        if (! $site->isHttpUrl($image)) {
            $image = null;
        }
        $description = $value('seo_description') ?: $value('og_description') ?: $settings['default_description'] ?: ($value($model instanceof Post ? 'excerpt' : 'summary') ?: $value('title'));

        return new Seo(
            title: $value('seo_title') ?: ($model instanceof Page && $model->is_home ? ($settings['default_title'] ?: $value('title')) : $value('title')),
            description: $description,
            canonical: $canonical,
            image: $image,
            alternates: $alternates,
            jsonLd: $includeAlternates ? $this->structuredData($model, $locale, $canonical, $image, $description) : [],
            robots: $robots,
            type: $value('og_type') ?: ($model instanceof Post ? 'article' : 'website'),
            ogTitle: $value('og_title') ?: null,
            ogDescription: $value('og_description') ?: null,
            twitterCard: $value('twitter_card') ?: $settings['twitter_card'],
            siteName: $settings['site_name'],
            locale: $settings['og_locale'] ?: null,
        );
    }

    public function forBlogIndex(?string $title = null, ?string $description = null, ?string $locale = null, bool $includeQuery = true): Seo
    {
        $locale ??= app()->getLocale();
        $settings = app(SiteSeo::class)->settings($locale);
        $parameters = ['locale' => $locale];
        $page = $includeQuery ? max(1, (int) request('page', 1)) : 1;
        if ($page > 1) {
            $parameters['page'] = $page;
        }
        $filtered = $includeQuery && (filled(request('q')) || filled(request('category')) || filled(request('tag')));
        $alternates = [];
        if (! $filtered && (new Seo(title: '', robots: $settings['robots']))->isIndexable()) {
            foreach (array_keys(config('noros.locales')) as $language) {
                if ((new Seo(title: '', robots: app(SiteSeo::class)->settings($language)['robots']))->isIndexable()) {
                    $alternates[$language] = route('blog.index', array_replace($parameters, ['locale' => $language]));
                }
            }
            $alternates['x-default'] = $alternates[$settings['x_default_locale']] ?? reset($alternates);
        }
        $organization = app(SiteSeo::class)->organization($locale);
        $image = app(MediaUrl::class)->resolve($settings['default_image']);

        return new Seo(
            title: $title ?: __('navigation.blog', locale: $locale).' | '.$settings['site_name'],
            description: $description ?: $settings['default_description'] ?: null,
            canonical: route('blog.index', $parameters),
            image: app(SiteSeo::class)->isHttpUrl($image) ? $image : null,
            alternates: $alternates,
            jsonLd: $organization ? ['@context' => 'https://schema.org', '@graph' => [$organization]] : [],
            robots: $filtered ? 'noindex, follow' : $settings['robots'],
            twitterCard: $settings['twitter_card'], siteName: $settings['site_name'], locale: $settings['og_locale'] ?: null,
        );
    }

    private function structuredData(Page|Post|PortfolioProject $model, string $locale, string $canonical, ?string $image, ?string $description): array
    {
        $organization = app(SiteSeo::class)->organization($locale);
        $nodes = $organization ? [$organization] : [];
        $custom = $model->translated('structured_data', $locale);
        if (is_array($custom) && $custom !== [] && (! isset($custom['@graph']) || (is_array($custom['@graph']) && array_is_list($custom['@graph'])))) {
            $nodes = array_merge($nodes, is_array($custom['@graph'] ?? null) ? $custom['@graph'] : (array_is_list($custom) ? $custom : [$custom]));
        } elseif ($model instanceof Post) {
            $article = ['@type' => 'BlogPosting', '@id' => $canonical.'#article', 'headline' => $model->translated('title', $locale),
                'description' => $description, 'mainEntityOfPage' => $canonical, 'inLanguage' => $locale,
                'datePublished' => $model->published_at?->toAtomString(), 'dateModified' => $model->updated_at?->toAtomString()];
            if ($image) {
                $article['image'] = $image;
            }
            if ($organization) {
                $article['publisher'] = ['@id' => $organization['@id']];
            }
            $authorName = $model->author?->getAttribute('name');
            if ($authorName) {
                $article['author'] = ['@type' => 'Person', 'name' => $authorName];
            }
            $nodes[] = array_filter($article, fn (mixed $value): bool => $value !== null);
        }

        return $nodes === [] ? [] : ['@context' => 'https://schema.org', '@graph' => $nodes];
    }
}
