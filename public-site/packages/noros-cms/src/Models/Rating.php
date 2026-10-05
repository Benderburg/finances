<?php

namespace Noros\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;
use Noros\Cms\Support\EngagementSettings;
use Noros\Core\Support\CacheService;

/**
 * @property int $score
 * @property string $rateable_type
 * @property int $rateable_id
 * @property string $visitor_hash
 */
class Rating extends Model
{
    protected $table = 'cms_ratings';

    protected $fillable = ['rateable_type', 'rateable_id', 'visitor_hash', 'score', 'user_id', 'comment_id'];

    protected $hidden = ['visitor_hash'];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(CacheService::class)->invalidate('engagement'));
        static::deleted(fn () => app(CacheService::class)->invalidate('engagement'));
        static::saving(function (self $rating): void {
            $scale = app(EngagementSettings::class)->scale($rating->rateable_type);
            if ($rating->score < $scale['min'] || $rating->score > $scale['max']) {
                throw ValidationException::withMessages(['score' => __('noros-cms::engagement.invalid_score')]);
            }
            if ($rating->comment_id) {
                $comment = Comment::find($rating->comment_id);
                if (! $comment || $comment->parent_id || $comment->entityKey() !== [$rating->rateable_type, (int) $rating->rateable_id]) {
                    throw ValidationException::withMessages(['score' => __('noros-cms::engagement.review_only')]);
                }
            }
        });
    }

    /** @return BelongsTo<Comment, $this> */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function rateable(): MorphTo
    {
        return $this->morphTo();
    }
}
