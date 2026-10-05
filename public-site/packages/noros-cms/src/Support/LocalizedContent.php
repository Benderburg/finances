<?php

namespace Noros\Cms\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Noros\Cms\Models\Category;
use Noros\Cms\Models\ContentTranslation;
use Noros\Cms\Models\Page;
use Noros\Cms\Models\TranslatableModel;
use Noros\Core\Support\SiteSeo;
use Noros\Core\Support\TranslationLibrary;

class LocalizedContent
{
    private array $cache = [];

    /** Preload translations for a bounded result set and its already eager-loaded relations. */
    public function warm(iterable $models): void
    {
        $queue = collect($models)->values()->all();
        $seen = [];
        $groups = [];
        $locales = array_unique([app()->getLocale(), config('noros.fallback_locale')]);
        while ($model = array_pop($queue)) {
            if (! $model instanceof Model) {
                continue;
            }
            $identity = $model->getTable().':'.$model->getKey();
            if (isset($seen[$identity])) {
                continue;
            }
            $seen[$identity] = true;
            foreach ($model->getRelations() as $relation) {
                if ($relation instanceof Model) {
                    $queue[] = $relation;
                } elseif ($relation instanceof Collection) {
                    array_push($queue, ...$relation->all());
                }
            }
            if ($model instanceof TranslatableModel) {
                $groups[$model->getTable()][] = $model->getKey();
            }
        }
        foreach ($groups as $table => $ids) {
            $ids = array_values(array_filter(array_unique($ids), function ($id) use ($table, $locales): bool {
                foreach ($locales as $locale) {
                    if (! array_key_exists($table.':'.$id.':'.$locale, $this->cache)) {
                        return true;
                    }
                }

                return false;
            }));
            if ($ids === []) {
                continue;
            }
            foreach ($ids as $id) {
                foreach ($locales as $locale) {
                    $this->cache[$table.':'.$id.':'.$locale] = [];
                }
            }
            foreach (ContentTranslation::where('entity_type', $table)->whereIn('entity_id', $ids)->whereIn('locale', $locales)->get() as $translation) {
                $this->cache[$table.':'.$translation->entity_id.':'.$translation->locale] = $translation->fields ?? [];
            }
        }
    }

    public function reindex(Model $model, ?string $oldPath = null): void
    {
        $this->cache = [];
        if ($model instanceof Page) {
            $model->unsetRelation('parent');
        }
        foreach (array_keys(config('noros.locales')) as $locale) {
            $row = ContentTranslation::firstOrNew(['entity_type' => $model->getTable(), 'entity_id' => $model->getKey(), 'locale' => $locale]);
            $path = $this->path($model, $locale);
            $this->assertAvailable($model, $locale, $path);
            $this->rememberPath($model, $locale, $row->path ?? $oldPath, $path);
            $row->fill(['path' => $path, 'fields' => $row->fields ?? []])->save();
            if ($model instanceof Page) {
                $this->refreshChildren($model, $locale);
            }
        }
    }

    public function fields(Model $model, string $locale): array
    {
        $key = $model->getTable().':'.$model->getKey().':'.$locale;

        return $this->cache[$key] ??= ContentTranslation::query()->where('entity_type', $model->getTable())->where('entity_id', $model->getKey())->where('locale', $locale)->first()->fields ?? [];
    }

    public function value(Model $model, string $field, ?string $locale = null, bool $fallback = true): mixed
    {
        $locale ??= app()->getLocale();
        $value = $this->fields($model, $locale)[$field] ?? null;
        if ($value !== null && $value !== '') {
            return $value;
        }
        if (! $fallback) {
            return null;
        }
        $value = $this->fields($model, config('noros.fallback_locale'))[$field] ?? null;

        return $value !== null && $value !== '' ? $value : ($model->getAttributes()[$field] ?? null);
    }

    public function save(TranslatableModel $model, string $locale, array $fields): ContentTranslation
    {
        if (! isset(config('noros.locales')[$locale])) {
            throw ValidationException::withMessages(['locale' => 'Unsupported locale.']);
        }
        $allowed = $model->translatableFields();
        if (array_diff(array_keys($fields), $allowed)) {
            throw ValidationException::withMessages(['fields' => 'Unsupported content field.']);
        }
        foreach ($fields as $key => $value) {
            $expectsArray = $model->hasCast($key, ['array', 'json']);
            if ($value !== null && ($expectsArray ? ! is_array($value) : ! is_string($value))) {
                throw ValidationException::withMessages([$key => 'Expected text.']);
            }
            if ($expectsArray && is_array($value)) {
                $source = json_decode($model->getAttributes()[$key] ?? '[]', true);
                if ($key !== 'structured_data') {
                    $this->validateStructure($value, is_array($source) ? $source : [], $key);
                }
            }
            if ($key === 'canonical_url' && filled($value) && ! app(SiteSeo::class)->isHttpUrl($value)) {
                throw ValidationException::withMessages([$key => 'Expected an absolute HTTP(S) URL.']);
            }
            if ($key === 'structured_data' && is_array($value) && isset($value['@graph']) && (! is_array($value['@graph']) || ! array_is_list($value['@graph']))) {
                throw ValidationException::withMessages([$key => '@graph must be a JSON array.']);
            }
        }
        $slug = $fields['slug'] ?? null;
        if ($slug !== null && $slug !== '' && (! preg_match('/^[\pL\pN]+(?:[-_][\pL\pN]+)*$/u', $slug) || strlen($slug) > 190)) {
            throw ValidationException::withMessages(['slug' => 'Invalid URL segment.']);
        }

        return DB::transaction(function () use ($model, $locale, $fields): ContentTranslation {
            app(TranslationLibrary::class)->lockForWriting();
            $translation = ContentTranslation::firstOrNew(['entity_type' => $model->getTable(), 'entity_id' => $model->getKey(), 'locale' => $locale]);
            $oldPath = $translation->path ?? $this->path($model, $locale);
            $translation->fields = array_replace($translation->fields ?? [], $fields);
            $translation->slug = $translation->fields['slug'] ?? null;
            // Release the old index only inside the transaction, then compute using the new fields.
            $translation->path = null;
            $translation->save();
            $this->cache = [];
            if (in_array('slug', $model->getFillable(), true)) {
                $this->reindex($model);
                $this->rememberPath($model, $locale, $oldPath, $this->path($model, $locale));
            }

            return $translation->refresh();
        });
    }

    private function validateStructure(array $value, array $source, string $field): void
    {
        foreach ($value as $key => $item) {
            $sample = $source[$key] ?? (array_is_list($source) ? ($source[0] ?? null) : null);
            if (is_array($item)) {
                if (! is_array($sample)) {
                    throw ValidationException::withMessages([$field => 'Translation structure must match the source content. Add structural blocks to the source first.']);
                }
                $this->validateStructure($item, $sample, $field.'.'.$key);
            } elseif (is_array($sample) || (is_object($item))) {
                throw ValidationException::withMessages([$field => 'Translation structure must match the source content.']);
            }
            if ($field === 'blocks' && (! is_array($item) || ! is_string($item['type'] ?? null) || ! is_array($item['data'] ?? null))) {
                throw ValidationException::withMessages([$field => 'Each block must contain a string type and an object data.']);
            }
        }
    }

    public function findTaxonomy(string $modelClass, string $slug, ?string $locale = null): ?Model
    {
        $locale ??= app()->getLocale();
        $model = new $modelClass;
        $row = ContentTranslation::query()->where('entity_type', $model->getTable())->where('locale', $locale)->where('path', $slug)->first();
        $query = $model->newQuery();
        if ($model instanceof Category) {
            $query->where('is_active', true);
        }

        return $row ? $query->find($row->entity_id) : $query->where('slug', $slug)->first();
    }

    public function path(Model $model, ?string $locale = null, ?string $slug = null): string
    {
        $locale ??= app()->getLocale();
        $slug = filled($slug) ? $slug : $this->value($model, 'slug', $locale);
        if ($model instanceof Page) {
            if ($model->getAttributes()['is_home'] ?? false) {
                return '';
            }

            return trim(($model->parent ? $this->path($model->parent, $locale) : '').'/'.$slug, '/');
        }

        return (string) $slug;
    }

    /** @template T of \Noros\Cms\Models\Page|\Noros\Cms\Models\Post|\Noros\Cms\Models\PortfolioProject
     * @param  class-string<T>  $modelClass
     * @return T|null
     */
    public function find(string $modelClass, string $path, ?string $locale = null): ?Model
    {
        $locale ??= app()->getLocale();
        $model = new $modelClass;
        $translation = ContentTranslation::query()->where('entity_type', $model->getTable())->where('locale', $locale)->where('path', $path)->first();
        if ($translation) {
            return $modelClass::query()->published()->find($translation->entity_id);
        }
        $candidate = $modelClass::query()->published()->where($model instanceof Page ? 'path' : 'slug', $path)->first();
        if ($candidate && $this->path($candidate, $locale) === $path) {
            return $candidate;
        }
        // A child can inherit a translated ancestor while its own fields remain untranslated.
        if ($model instanceof Page) {
            $slug = basename($path);
            foreach ($modelClass::query()->published()->where('slug', $slug)->get() as $page) {
                if ($this->path($page, $locale) === $path) {
                    return $page;
                }
            }
        }

        return null;
    }

    /** @template T of \Noros\Cms\Models\Page|\Noros\Cms\Models\Post|\Noros\Cms\Models\PortfolioProject
     * @param  class-string<T>  $modelClass
     * @return T|null
     */
    public function redirect(string $modelClass, string $path, string $locale): ?Model
    {
        $model = new $modelClass;
        $row = DB::table('cms_slug_redirects')->where('entity_type', $model->getTable())->where('locale', $locale)->where('path', $path)->first();

        return $row ? $modelClass::query()->published()->find($row->entity_id) : null;
    }

    private function assertAvailable(Model $model, string $locale, string $path): void
    {
        $conflict = ContentTranslation::query()->where('entity_type', $model->getTable())->where('locale', $locale)->where('path', $path)->where('entity_id', '!=', $model->getKey())->exists();
        $sourceConflict = $model->newQuery()->where($model instanceof Page ? 'path' : 'slug', $path)->whereKeyNot($model->getKey())->exists();
        if (strlen($path) > 190 || $conflict || $sourceConflict || ($model instanceof Page && preg_match('~^(?:(?:admin|blog|shop|search|sitemap\.xml|robots\.txt)(?:/|$)|portfolio/|contact/)~', $path))) {
            throw ValidationException::withMessages(['slug' => 'URL is reserved or already in use.']);
        }
    }

    private function rememberPath(Model $model, string $locale, ?string $old, string $new): void
    {
        if ($old !== null && $old !== $new) {
            DB::table('cms_slug_redirects')->updateOrInsert(['entity_type' => $model->getTable(), 'locale' => $locale, 'path' => $old], ['entity_id' => $model->getKey()]);
        }
        DB::table('cms_slug_redirects')->where('entity_type', $model->getTable())->where('locale', $locale)->where('path', $new)->delete();
    }

    private function refreshChildren(Page $parent, string $locale): void
    {
        foreach ($parent->children()->get() as $child) {
            $translation = ContentTranslation::firstOrNew(['entity_type' => 'pages', 'entity_id' => $child->id, 'locale' => $locale]);
            $old = $translation->path ?? $child->getRawOriginal('path');
            $path = $this->path($child, $locale);
            $this->assertAvailable($child, $locale, $path);
            $this->rememberPath($child, $locale, $old, $path);
            $translation->fill(['path' => $path, 'fields' => $translation->fields ?? []])->save();
            $this->refreshChildren($child, $locale);
        }
    }
}
