<?php

namespace Noros\Cms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Noros\Cms\Contracts\EngagementChallenge;
use Noros\Cms\Models\Comment;
use Noros\Cms\Support\EngagementService;
use Noros\Cms\Support\VisitorIdentity;

class EngagementController extends Controller
{
    public function index(Request $request, string $type, int $id, EngagementService $service): JsonResponse
    {
        $service->resolve($type, $id);
        $input = $request->validate(['page' => ['nullable', 'integer', 'between:1,100000'], 'parent_id' => ['nullable', 'integer', 'min:1']], $service->validationMessages());
        $parentId = $input['parent_id'] ?? null;
        if ($parentId) {
            abort_unless(Comment::whereKey($parentId)->forEntity($type, $id)->approved()->whereNull('parent_id')->exists(), 404);
        }
        $comments = Comment::forEntity($type, $id)->approved()
            ->where('parent_id', $parentId)->with('rating')->oldest('id')->paginate(10);

        return response()->json(['data' => $comments->map(fn ($comment): array => [
            'id' => $comment->id, 'author' => $comment->author_name, 'body' => $comment->body,
            'created_at' => $comment->created_at->toIso8601String(), 'score' => $comment->rating?->score,
        ]), 'page' => $comments->currentPage(), 'last_page' => $comments->lastPage()]);
    }

    public function comment(Request $request, string $type, int $id, EngagementService $service): JsonResponse
    {
        app(EngagementChallenge::class)->validate($request);
        $identity = app(VisitorIdentity::class);
        $comment = $service->comment($type, $id, $request->all(), $request->user()?->getAuthIdentifier(), visitorHash: $identity->hash($request));

        return response()->json(['id' => $comment->getKey(), 'status' => $comment->status->value], 201)
            ->cookie('noros_visitor', $identity->token($request), 525600, '/', null, $request->isSecure(), true, false, 'lax');
    }

    public function rate(Request $request, string $type, int $id, EngagementService $service): JsonResponse
    {
        app(EngagementChallenge::class)->validate($request);
        $input = $request->validate(['score' => ['required', 'integer'], 'company' => ['nullable', 'string', 'max:0']], $service->validationMessages());
        // The web middleware authenticates/encrypts this HttpOnly cookie. The session retains
        // its value too, so deleting just the cookie does not create an extra voter.
        $identity = app(VisitorIdentity::class);
        $token = $identity->token($request);
        $userId = $request->user()?->getAuthIdentifier();
        $hash = $identity->hash($request);
        $networkKey = 'noros-rating:'.hash_hmac('sha256', $type.':'.$id.':'.$request->ip(), (string) config('app.key'));
        abort_if(RateLimiter::tooManyAttempts($networkKey, 10), 429);
        RateLimiter::hit($networkKey, 3600);
        $rating = $service->rate($type, $id, (int) $input['score'], $hash, $userId);
        $summary = $service->summary($type, $id);

        return response()->json(['score' => $rating->score, 'average' => $summary['average'], 'count' => $summary['count']])->cookie('noros_visitor', $token, 525600, '/', null, $request->isSecure(), true, false, 'lax');
    }
}
