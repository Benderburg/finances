<?php

namespace Noros\Cms\Support;

use Illuminate\Database\Eloquent\Builder;
use Noros\Cms\Models\Category;
use Noros\Cms\Models\Post;
use Noros\Cms\Models\Tag;
use Noros\Core\Support\CacheService;

class BlogSidebarData
{
    /** @return array<string, mixed> */
    public function get(?Post $currentPost = null): array
    {
        $data = app(CacheService::class)->remember('cms', 'blog-sidebar:'.($currentPost->id ?? 0).':'.app()->getLocale(), fn (): array => [
            'recentPosts' => Post::query()
                ->published()
                ->when($currentPost, fn (Builder $query): Builder => $query->whereKeyNot($currentPost->id))
                ->latest('published_at')
                ->limit(3)
                ->get(),
            'blogCategories' => Category::query()
                ->where('is_active', true)
                ->withCount(['posts as published_posts_count' => fn ($query) => $query->published()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'popularTags' => Tag::query()
                ->whereHas('posts', fn ($query) => (new Post)->scopePublished($query))
                ->withCount(['posts as published_posts_count' => fn ($query) => $query->published()])
                ->orderByDesc('published_posts_count')
                ->orderBy('name')
                ->limit(12)
                ->get(),
        ], min(60, (int) config('noros-cache.ttls.cms', 300)));
        app(LocalizedContent::class)->warm(collect($data)->flatten(1));

        return $data;
    }
}
