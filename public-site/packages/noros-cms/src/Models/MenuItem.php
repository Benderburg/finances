<?php

namespace Noros\Cms\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Noros\Core\Support\LocalUrl;

class MenuItem extends TranslatableModel
{
    protected $fillable = [
        'code',
        'menu_id',
        'parent_id',
        'page_id',
        'post_id',
        'type',
        'label',
        'url',
        'target',
        'css_class',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MenuItem $item): void {
            if (! $item->parent_id) {
                return;
            }

            $parent = static::query()->find($item->parent_id);

            if (! $parent || (int) $parent->menu_id !== (int) $item->menu_id) {
                throw ValidationException::withMessages(['parent_id' => 'Родитель должен находиться в том же меню.']);
            }

            while ($parent) {
                if ((int) $parent->getKey() === (int) $item->getKey()) {
                    throw ValidationException::withMessages(['parent_id' => 'Нельзя создавать циклическую вложенность меню.']);
                }

                $parent = $parent->parent;
            }
        });
    }

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function getResolvedUrlAttribute(): string
    {
        return match ($this->type) {
            'page' => $this->page->url ?? '#',
            'blog_index' => route('blog.index'),
            'blog_post' => $this->post ? route('blog.show', $this->post) : '#',
            default => $this->customUrl(),
        };
    }

    public function getIsAvailableAttribute(): bool
    {
        return match ($this->type) {
            'page' => config('noros-cms.features.pages') && (bool) $this->page?->isPublished(),
            'blog_index' => (bool) config('noros-cms.features.blog'),
            'blog_post' => config('noros-cms.features.blog') && (bool) $this->post?->isPublished(),
            default => true,
        };
    }

    public function getIsCurrentAttribute(): bool
    {
        $target = rtrim($this->resolved_url, '/');
        $current = rtrim(url()->current(), '/');

        $home = rtrim(route('home'), '/');

        return $target !== '' && (
            $current === $target
            || ($target !== $home && str_starts_with($current.'/', $target.'/'))
        );
    }

    private function customUrl(): string
    {
        if (blank($this->url)) {
            return '#';
        }

        return app(LocalUrl::class)->resolve($this->url);
    }
}
