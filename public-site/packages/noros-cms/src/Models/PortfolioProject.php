<?php

namespace Noros\Cms\Models;

use Illuminate\Database\Eloquent\Builder;

class PortfolioProject extends TranslatableModel
{
    protected static function booted(): void
    {
        static::saving(function (self $project): void {
            if ($project->status === 'published' && ! $project->published_at) {
                $project->published_at = now();
            }
        });
    }

    protected $fillable = ['canonical_url', 'seo_robots', 'og_title', 'og_description', 'og_image', 'og_type', 'twitter_card', 'structured_data', 'tags', 'technologies', 'client', 'year', 'project_url', 'gallery', 'is_featured', 'task', 'solution', 'result', 'slug', 'title', 'heading', 'category', 'summary', 'content', 'image', 'seo_title', 'seo_description', 'seo_keywords', 'related_slugs', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['structured_data' => 'array', 'tags' => 'array', 'technologies' => 'array', 'gallery' => 'array', 'is_featured' => 'boolean', 'year' => 'integer', 'content' => 'array', 'related_slugs' => 'array', 'published_at' => 'datetime'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function getUrlAttribute(): string
    {
        return route('portfolio.case', ['case' => $this->slug]);
    }
}
