<?php

namespace Noros\Cms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Noros\Cms\Database\Factories\CommentFactory;
use Noros\Cms\Enums\CommentStatus;
use Noros\Cms\Events\CommentSubmitted;
use Noros\Core\Models\User;
use Noros\Core\Support\CacheService;

/**
 * @property CommentStatus $status
 * @property Carbon|null $approved_at
 */
class Comment extends Model
{
    use HasFactory;

    protected static function newFactory(): CommentFactory
    {
        return CommentFactory::new();
    }

    protected $fillable = [
        'post_id',
        'commentable_type',
        'commentable_id',
        'parent_id',
        'user_id',
        'author_name',
        'author_email',
        'author_website',
        'body',
        'status',
        'ip_address',
        'user_agent',
        'approved_at',
        'submission_hash',
    ];

    protected $hidden = [
        'author_email',
        'ip_address',
        'user_agent',
        'submission_hash',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(CacheService::class)->invalidate('engagement'));
        static::deleted(fn () => app(CacheService::class)->invalidate('engagement'));
        static::saving(function (Comment $comment): void {
            if ($comment->post_id && ! $comment->commentable_type) {
                $comment->commentable_type = 'post';
                $comment->commentable_id = $comment->post_id;
            }
            if ($comment->commentable_type === 'post') {
                if ($comment->post_id && (int) $comment->post_id !== (int) $comment->commentable_id) {
                    throw ValidationException::withMessages(['post_id' => 'The comment target cannot disagree with its post.']);
                }
                $comment->post_id = $comment->commentable_id;
            } elseif ($comment->commentable_type) {
                $comment->post_id = null;
            }
            if ($comment->parent_id) {
                $parent = self::find($comment->parent_id);
                if (! $parent || $parent->id === $comment->id || $parent->entityKey() !== $comment->entityKey()) {
                    throw ValidationException::withMessages(['parent_id' => 'The parent must belong to the same content.']);
                }
                $visited = [];
                while ($parent) {
                    if (isset($visited[$parent->id]) || $parent->id === $comment->id) {
                        throw ValidationException::withMessages(['parent_id' => 'Reply cycles are not allowed.']);
                    }
                    $visited[$parent->id] = true;
                    $parent = $parent->parent_id ? self::find($parent->parent_id) : null;
                }
            }
            if ($comment->status === CommentStatus::Approved) {
                $comment->approved_at ??= now();
            } else {
                $comment->approved_at = null;
            }
        });
        static::created(function (Comment $comment): void {
            if ($comment->status === CommentStatus::Pending) {
                CommentSubmitted::dispatch($comment);
            }
        });
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasOne<Rating, $this> */
    public function rating(): HasOne
    {
        return $this->hasOne(Rating::class);
    }

    /** @return array{0: string, 1: int} */
    public function entityKey(): array
    {
        return [$this->commentable_type ?? 'post', (int) ($this->commentable_id ?? $this->post_id)];
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->approved()->oldest();
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class));
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', CommentStatus::Approved->value);
    }

    public function scopeForEntity(Builder $query, string $type, int $id): Builder
    {
        return $query->where(function (Builder $target) use ($type, $id): void {
            $target->where(fn (Builder $morph) => $morph->where('commentable_type', $type)->where('commentable_id', $id));
            if ($type === 'post') {
                $target->orWhere(fn (Builder $legacy) => $legacy->whereNull('commentable_type')->where('post_id', $id));
            }
        });
    }
}
