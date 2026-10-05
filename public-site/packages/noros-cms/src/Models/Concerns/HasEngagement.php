<?php

namespace Noros\Cms\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Noros\Cms\Models\Comment;
use Noros\Cms\Models\Rating;
use Noros\Cms\Support\EngagementService;
use Noros\Cms\Support\EngagementSettings;
use Noros\Core\Support\CacheService;

trait HasEngagement
{
    /** @return MorphMany<Comment, $this> */
    public function engagementComments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /** @return MorphMany<Rating, $this> */
    public function ratingScores(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    public function averageRating(): float
    {
        return app(EngagementService::class)->summary($this->getMorphClass(), $this->getKey())['average'];
    }

    public function ratingCount(): int
    {
        return app(EngagementService::class)->summary($this->getMorphClass(), $this->getKey())['count'];
    }

    public function allowsComments(): bool
    {
        return app(EngagementSettings::class)->enabled($this, 'comments');
    }

    public function allowsRatings(): bool
    {
        return app(EngagementSettings::class)->enabled($this, 'ratings');
    }

    public static function bootHasEngagement(): void
    {
        static::deleting(function ($record): void {
            $record->engagementComments()->delete();
            $record->ratingScores()->delete();
            app(CacheService::class)->invalidate('engagement');
        });
    }
}
