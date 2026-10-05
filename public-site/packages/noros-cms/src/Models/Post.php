<?php

namespace Noros\Cms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Noros\Cms\Database\Factories\PostFactory;
use Noros\Cms\Enums\PostStatus;
use Noros\Cms\Models\Concerns\HasEngagement;
use Noros\Core\Models\User;

/**
 * @property PostStatus $status
 * @property Carbon|null $published_at
 */
class Post extends TranslatableModel
{
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $locale = app()->getLocale();
        $fallback = config('noros.fallback_locale');

        return $query->where(function (Builder $query) use ($locale, $fallback, $search): void {
            foreach (['title', 'subtitle', 'excerpt', 'content'] as $field) {
                $translation = fn (string $language) => function (\Illuminate\Database\Query\Builder $subquery) use ($language, $field): void {
                    $subquery->selectRaw('1')->from('cms_content_translations')->whereColumn('entity_id', 'posts.id')->where('entity_type', 'posts')->where('locale', $language)->whereNotNull('fields->'.$field)->where('fields->'.$field, '!=', '');
                };
                $matching = fn (string $language) => function (\Illuminate\Database\Query\Builder $subquery) use ($translation, $language, $field, $search): void {
                    ($translation($language))($subquery);
                    $subquery->where('fields->'.$field, 'like', '%'.$search.'%');
                };
                $query->orWhere(function (Builder $fieldQuery) use ($translation, $matching, $locale, $fallback, $field, $search): void {
                    $fieldQuery->whereExists($matching($locale))->orWhere(function (Builder $fallbackQuery) use ($translation, $matching, $locale, $fallback, $field, $search): void {
                        $fallbackQuery->whereNotExists($translation($locale))->where(function (Builder $sourceQuery) use ($translation, $matching, $fallback, $field, $search): void {
                            $sourceQuery->whereExists($matching($fallback))->orWhere(fn (Builder $rawQuery) => $rawQuery->whereNotExists($translation($fallback))->where('posts.'.$field, 'like', '%'.$search.'%'));
                        });
                    });
                });
            }
        });
    }

    use HasEngagement;
    use HasFactory;

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    protected $fillable = [
        'author_id',
        'category_id',
        'title',
        'slug',
        'subtitle',
        'excerpt',
        'content',
        'cover_image',
        'cover_alt',
        'status',
        'published_at',
        'is_featured',
        'allow_comments',
        'allow_ratings',
        'engagement_overrides',
        'reading_time',
        'views_count',
        'project_url',
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
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'allow_comments' => 'boolean',
            'allow_ratings' => 'boolean',
            'engagement_overrides' => 'array',
            'reading_time' => 'integer',
            'views_count' => 'integer',
            'structured_data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post): void {
            if (blank($post->slug)) {
                $post->slug = Str::slug($post->title);
            }

            if (blank($post->reading_time)) {
                preg_match_all('/[\p{L}\p{N}\x{2019}\x{0027}]+/u', strip_tags((string) $post->content), $words);
                $post->reading_time = max(1, (int) ceil(count($words[0]) / 180));
            }
        });
    }

    /** @return BelongsTo<Model, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'author_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return HasMany<Comment, $this> */
    public function approvedComments(): HasMany
    {
        return $this->comments()->approved();
    }

    /** @return HasMany<PostRating, $this> */
    public function ratings(): HasMany
    {
        return $this->hasMany(PostRating::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [PostStatus::Published->value, PostStatus::Scheduled->value])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return in_array($this->status, [PostStatus::Published, PostStatus::Scheduled], true)
            && $this->published_at?->isPast();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getCoverImageUrlAttribute(): string
    {
        return $this->mediaUrl($this->cover_image, 'img/portfolio/portfolio-1.jpg');
    }

    public function getOgImageUrlAttribute(): string
    {
        return $this->mediaUrl($this->og_image ?: $this->cover_image, 'img/portfolio/portfolio-1.jpg');
    }

    public function getSummaryAttribute(): string
    {
        return $this->excerpt ?: Str::limit(trim(strip_tags((string) $this->content)), 190);
    }

    private function mediaUrl(?string $path, string $fallback): string
    {
        if (blank($path)) {
            return asset($fallback);
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        if (Str::startsWith($path, ['img/', '/img/'])) {
            return asset(ltrim($path, '/'));
        }

        return Storage::disk('public')->url($path);
    }
}
