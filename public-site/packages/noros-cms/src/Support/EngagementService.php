<?php

namespace Noros\Cms\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Noros\Cms\Enums\CommentStatus;
use Noros\Cms\Models\Comment;
use Noros\Cms\Models\Post;
use Noros\Cms\Models\Rating;
use Noros\Core\Support\CacheService;

class EngagementService
{
    public function resolve(string $type, int $id, bool $lock = false): Model
    {
        $models = config('noros-cms.engagement.models', ['post' => Post::class]);
        $class = $models[$type] ?? null;
        abort_unless(is_string($class) && is_a($class, Model::class, true), 404);
        $query = $class::query()->whereKey($id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $entity = $query->firstOrFail();
        $published = method_exists($entity, 'isPublished') ? $entity->isPublished() : $entity->getAttribute('status') === 'published';
        if (! method_exists($entity, 'isPublished') && $entity->getAttribute('published_at') !== null) {
            $published = $published && Carbon::parse($entity->getAttribute('published_at'))->lte(now());
        }
        abort_unless($published, 404);

        return $entity;
    }

    public function comment(string $type, int $id, array $input, ?int $userId = null, ?string $ip = null, ?string $userAgent = null, ?string $visitorHash = null): Comment
    {
        foreach (['author_name', 'author_email', 'body'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        $data = Validator::make($input, [
            'author_name' => ['required', 'string', 'min:2', 'max:100'],
            'author_email' => ['required', 'email:rfc', 'max:190'],
            'body' => ['required', 'string', 'min:3', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'consent' => ['accepted'],
            'company' => ['nullable', 'string', 'max:0'],
            'score' => ['nullable', 'integer'],
        ], $this->validationMessages())->validate();

        return DB::transaction(function () use ($type, $id, $data, $userId, $visitorHash): Comment {
            $entity = $this->resolve($type, $id, true);
            $settings = app(EngagementSettings::class);
            $module = $settings->module($type);
            abort_unless($settings->enabled($entity, 'comments'), 403);
            abort_unless($userId || $settings->permitsGuest($type, 'comments'), 403, __('noros-cms::engagement.login_required'));
            if ($type === 'product' && $module['verified_purchase']) {
                $verified = $userId && DB::table('shop_orders')->where('user_id', $userId)->where('status', 'completed')
                    ->whereExists(fn ($query) => $query->selectRaw('1')->from('shop_order_items')->whereColumn('shop_order_items.order_id', 'shop_orders.id')->where('product_id', $id))->exists();
                abort_unless($verified, 403, __('noros-cms::engagement.purchase_required'));
            }
            if (! empty($data['parent_id'])) {
                $parent = Comment::whereKey($data['parent_id'])->lockForUpdate()->first();
                if (! $parent || $parent->entityKey() !== [$type, $id] || $parent->status !== CommentStatus::Approved || $parent->parent_id) {
                    throw ValidationException::withMessages(['parent_id' => __('noros-cms::engagement.invalid_parent')]);
                }
            }
            $fingerprint = hash_hmac('sha256', json_encode([$type, $id, $data['parent_id'] ?? null, $userId, strtolower($data['author_email']), $data['body']], JSON_THROW_ON_ERROR), (string) config('app.key'));
            $existing = Comment::where('submission_hash', $fingerprint)->first();
            if ($existing) {
                throw ValidationException::withMessages(['body' => __('noros-cms::engagement.duplicate')]);
            }
            $rating = null;
            if (isset($data['score'])) {
                if ($type !== 'product' || ! empty($data['parent_id'])) {
                    throw ValidationException::withMessages(['score' => __('noros-cms::engagement.review_only')]);
                }
                $rating = $this->rate($type, $id, (int) $data['score'], $visitorHash ?? hash('sha256', (string) Str::uuid()), $userId);
                if ($rating->comment_id) {
                    throw ValidationException::withMessages(['body' => __('noros-cms::engagement.review_exists')]);
                }
            }
            $comment = Comment::create([
                'post_id' => $type === 'post' ? $id : null,
                'commentable_type' => $type, 'commentable_id' => $id, 'parent_id' => $data['parent_id'] ?? null,
                'user_id' => $userId, 'author_name' => trim($data['author_name']), 'author_email' => strtolower(trim($data['author_email'])),
                'body' => trim($data['body']), 'status' => $module['moderation'] ? CommentStatus::Pending : CommentStatus::Approved,
                'submission_hash' => $fingerprint,
            ]);
            if ($rating) {
                $rating->update(['comment_id' => $comment->id]);
            }

            return $comment;
        }, 3);
    }

    public function rate(string $type, int $id, int $score, string $visitorHash, ?int $userId = null): Rating
    {
        $settings = app(EngagementSettings::class);
        $scale = $settings->scale($type);
        Validator::make(['score' => $score, 'visitor' => $visitorHash], ['score' => ['required', 'integer', 'between:'.$scale['min'].','.$scale['max']], 'visitor' => ['required', 'regex:/^[a-f0-9]{64}$/']], $this->validationMessages())->validate();
        if ($userId) {
            $visitorHash = hash_hmac('sha256', 'user:'.$userId, (string) config('app.key'));
        }

        return DB::transaction(function () use ($type, $id, $score, $visitorHash, $userId): Rating {
            $entity = $this->resolve($type, $id, true);
            $settings = app(EngagementSettings::class);
            abort_unless($settings->enabled($entity, 'ratings'), 403);
            abort_unless($userId || $settings->permitsGuest($type, 'ratings'), 403, __('noros-cms::engagement.login_required'));
            $query = Rating::where('rateable_type', $type)->where('rateable_id', $id);
            $rating = (clone $query)->where('visitor_hash', $visitorHash)->first();
            // Existing authenticated votes survive a key rotation or older visitor identities.
            $rating ??= $userId ? (clone $query)->where('user_id', $userId)->oldest('id')->first() : null;
            if ($rating && ! $settings->module($type)['allow_change_vote']) {
                throw ValidationException::withMessages(['score' => __('noros-cms::engagement.vote_exists')]);
            }
            if ($rating) {
                $rating->update(['score' => $score]);
                if ($rating->comment_id && $settings->module($type)['moderation']) {
                    $rating->comment?->update(['status' => CommentStatus::Pending]);
                }

                return $rating;
            }

            return Rating::create(['rateable_type' => $type, 'rateable_id' => $id, 'visitor_hash' => $visitorHash, 'score' => $score, 'user_id' => $userId]);
        }, 3);
    }

    public function summary(string $type, int $id): array
    {
        return app(CacheService::class)->remember('engagement', $type.':'.$id, function () use ($type, $id): array {
            $scores = Rating::where('rateable_type', $type)->where('rateable_id', $id)->where(fn ($query) => $query->whereNull('comment_id')->orWhereHas('comment', fn ($comments) => $comments->where('status', CommentStatus::Approved->value)));
            $aggregate = $scores->selectRaw('COUNT(*) AS total, AVG(score) AS average')->first();

            return ['count' => (int) $aggregate->getAttribute('total'), 'average' => (float) ($aggregate->getAttribute('average') ?? 0),
                'comments' => Comment::forEntity($type, $id)->approved()->whereNull('parent_id')->count()];
        });
    }

    public function validationMessages(): array
    {
        $messages = [];
        foreach (['required', 'string', 'email', 'min', 'max', 'integer', 'between', 'accepted', 'regex'] as $rule) {
            $messages[$rule] = __('noros-cms::engagement.validation_'.$rule);
        }

        return $messages;
    }

    /** Prepared data only: the host decides whether to emit schema.org markup. */
    public function structuredRating(string $type, int $id): ?array
    {
        $summary = $this->summary($type, $id);
        if ($summary['count'] === 0) {
            return null;
        }
        $scale = app(EngagementSettings::class)->scale($type);

        return ['@type' => 'AggregateRating', 'ratingValue' => round($summary['average'], 2), 'ratingCount' => $summary['count'], 'bestRating' => $scale['max'], 'worstRating' => $scale['min']];
    }

    public function structuredReviews(string $type, int $id): array
    {
        $scale = app(EngagementSettings::class)->scale($type);

        return Comment::where('commentable_type', $type)->where('commentable_id', $id)->approved()->whereHas('rating')
            ->with('rating')->latest('id')->limit(10)->get()->map(fn (Comment $comment): array => [
                '@type' => 'Review', 'author' => ['@type' => 'Person', 'name' => $comment->author_name],
                'reviewBody' => $comment->body, 'datePublished' => $comment->approved_at?->toIso8601String(),
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $comment->rating->score, 'bestRating' => $scale['max'], 'worstRating' => $scale['min']],
            ])->all();
    }

    /** Explicit content migration, intentionally never called by a schema migration. */
    public function backfillLegacyComments(): int
    {
        return DB::table('comments')->whereNull('commentable_type')->whereNotNull('post_id')->update(['commentable_type' => 'post', 'commentable_id' => DB::raw('post_id')]);
    }
}
