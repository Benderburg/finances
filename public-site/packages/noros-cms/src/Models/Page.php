<?php

namespace Noros\Cms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Noros\Cms\Enums\PageStatus;
use Noros\Cms\Enums\PageTemplate;
use Noros\Cms\Support\LocalizedContent;

/**
 * @property PageStatus $status
 * @property PageTemplate $template
 * @property Carbon|null $published_at
 * @property list<array<string, mixed>>|null $blocks
 */
class Page extends TranslatableModel
{
    protected $fillable = [
        'parent_id',
        'title',
        'heading',
        'slug',
        'path',
        'template',
        'status',
        'published_at',
        'is_home',
        'blocks',
        'sidebar_content',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'canonical_url',
        'seo_robots',
        'og_title',
        'og_description',
        'og_image',
        'og_type',
        'twitter_card',
        'structured_data',
    ];

    protected function casts(): array
    {
        return [
            'template' => PageTemplate::class,
            'status' => PageStatus::class,
            'published_at' => 'datetime',
            'is_home' => 'boolean',
            'blocks' => 'array',
            'structured_data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Page $page): void {
            $page->slug = Str::slug($page->slug ?: $page->title);

            if ($page->parent_id && (int) $page->parent_id === (int) $page->getKey()) {
                throw ValidationException::withMessages(['parent_id' => 'Страница не может быть родителем самой себе.']);
            }

            if ($page->parent_id && $page->wouldCreateCycle()) {
                throw ValidationException::withMessages(['parent_id' => 'Нельзя выбрать дочернюю страницу в качестве родителя.']);
            }

            if ($page->is_home) {
                static::query()
                    ->where('is_home', true)
                    ->when($page->exists, fn (Builder $query): Builder => $query->whereKeyNot($page->getKey()))
                    ->get()
                    ->each(function (Page $oldHome): void {
                        $oldPath = $oldHome->getRawOriginal('path');
                        $oldHome->is_home = false;
                        $oldHome->path = $oldHome->slug;
                        $oldHome->saveQuietly();
                        $oldHome->refreshDescendantPaths();
                        app(LocalizedContent::class)->reindex($oldHome, $oldPath);
                    });
                $page->parent_id = null;
                $page->path = '';
            } else {
                $parentPath = $page->parent_id
                    ? (string) static::query()->whereKey($page->parent_id)->value('path')
                    : '';
                $page->path = trim($parentPath.'/'.$page->slug, '/');
            }

            if ($page->status === PageStatus::Published && ! $page->published_at) {
                $page->published_at = now();
            }
        });

        static::saved(function (Page $page): void {
            if ($page->wasChanged('path')) {
                $page->refreshDescendantPaths();
            }
        });
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('title');
    }

    /** @return HasMany<MenuItem, $this> */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [PageStatus::Published->value, PageStatus::Scheduled->value])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return in_array($this->status, [PageStatus::Published, PageStatus::Scheduled], true)
            && $this->published_at?->isPast();
    }

    public function getUrlAttribute(): string
    {
        return $this->is_home ? route('home') : route('pages.show', ['path' => app(LocalizedContent::class)->path($this)]);
    }

    public function getOgImageUrlAttribute(): ?string
    {
        return $this->mediaUrl($this->og_image);
    }

    /** @return array<int, array{title: string, url: string}> */
    public function breadcrumbs(): array
    {
        $items = [];
        $current = $this;

        while ($current) {
            array_unshift($items, ['title' => $current->title, 'url' => $current->url]);
            $current = $current->parent;
        }

        if (! $this->is_home && $items[0]['url'] !== route('home')) {
            array_unshift($items, ['title' => __('navigation.home'), 'url' => route('home')]);
        }

        return $items;
    }

    public function mediaUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        if (Str::startsWith($path, ['img/', '/img/'])) {
            return asset(ltrim($path, '/'));
        }

        return Storage::disk('public')->url($path);
    }

    private function wouldCreateCycle(): bool
    {
        $parentId = $this->parent_id;

        while ($parentId) {
            if ((int) $parentId === (int) $this->getKey()) {
                return true;
            }

            $parentId = static::query()->whereKey($parentId)->value('parent_id');
        }

        return false;
    }

    private function refreshDescendantPaths(): void
    {
        $this->children()->each(function (Page $child): void {
            $child->path = trim($this->path.'/'.$child->slug, '/');
            $child->saveQuietly();
            $child->refreshDescendantPaths();
        });
    }
}
