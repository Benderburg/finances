<?php

namespace Noros\Cms\Models\Concerns;

use Illuminate\Support\Facades\DB;
use Noros\Cms\Models\ContentTranslation;
use Noros\Cms\Models\Page;
use Noros\Cms\Support\LocalizedContent;

trait HasContentTranslations
{
    public function translationStatus(string $locale): string
    {
        $fields = app(LocalizedContent::class)->fields($this, $locale);
        $expected = array_filter($this->translatableFields(), fn (string $field): bool => filled($this->getAttributes()[$field] ?? null));
        $filled = array_filter($expected, fn (string $field): bool => filled($fields[$field] ?? null));

        return $filled === [] ? 'missing' : (count($filled) === count($expected) ? 'complete' : 'partial');
    }

    public function translatableFields(): array
    {
        return array_values(array_intersect($this->getFillable(), ['title', 'name', 'heading', 'slug', 'subtitle', 'excerpt', 'description', 'content', 'summary', 'category', 'blocks', 'sidebar_content', 'seo_title', 'seo_description', 'seo_keywords', 'canonical_url', 'seo_robots', 'og_title', 'og_description', 'og_image', 'og_type', 'twitter_card', 'structured_data', 'cover_alt', 'projects', 'plans', 'tasks', 'label', 'task', 'solution', 'result']));
    }

    public function translated(string $field, ?string $locale = null, bool $fallback = true): mixed
    {
        $value = app(LocalizedContent::class)->value($this, $field, $locale, $fallback);
        if (is_string($value) && $this->hasCast($field, ['array', 'json'])) {
            return json_decode($value, true);
        }

        return $value;
    }

    public function getAttribute($key): mixed
    {
        if ($this->useTranslatedAttributes && $this->exists && in_array($key, $this->translatableFields(), true) && app()->bound('request') && request()->attributes->has('noros.locale')) {
            return $this->translated($key);
        }

        return parent::getAttribute($key);
    }

    protected static function bootHasContentTranslations(): void
    {
        static::saved(function ($model): void {
            if (in_array('slug', $model->getFillable(), true) && ($model->wasRecentlyCreated || $model->wasChanged(['slug', 'path', 'parent_id', 'is_home']))) {
                app(LocalizedContent::class)->reindex($model, $model->getRawOriginal($model instanceof Page ? 'path' : 'slug'));
            }
        });
        static::deleted(function ($model): void {
            ContentTranslation::query()->where('entity_type', $model->getTable())->where('entity_id', $model->getKey())->delete();
            DB::table('cms_slug_redirects')->where('entity_type', $model->getTable())->where('entity_id', $model->getKey())->delete();
        });
    }
}
