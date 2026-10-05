<?php

namespace Noros\Cms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Noros\Cms\Contracts\EngagementChallenge;
use Noros\Cms\Models\Post;
use Noros\Cms\Support\EngagementService;
use Noros\Cms\Support\EngagementSettings;
use Noros\Cms\Support\LocalizedContent;

class PostRatingController extends Controller
{
    public function toggle(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        $post = app(LocalizedContent::class)->find(Post::class, $slug);
        abort_unless($post !== null, 404);
        $settings = app(EngagementSettings::class);
        abort_unless($settings->enabled($post, 'ratings'), 403);
        abort_unless($request->user() || $settings->permitsGuest('post', 'ratings'), 403);
        app(EngagementChallenge::class)->validate($request);
        $request->validate(['company' => ['nullable', 'string', 'max:0']], app(EngagementService::class)->validationMessages());
        $visitorId = $request->session()->get('noros.blog_visitor') ?? $request->cookie('blog_visitor');
        if (! is_string($visitorId) || ! Str::isUuid($visitorId)) {
            $visitorId = (string) Str::uuid();
        }
        $request->session()->put('noros.blog_visitor', $visitorId);
        $visitorHash = $request->user() ? hash_hmac('sha256', 'user:'.$request->user()->getAuthIdentifier(), (string) config('app.key')) : hash('sha256', $visitorId.config('app.key'));
        $networkKey = 'noros-like:'.hash_hmac('sha256', $post->id.':'.$request->ip(), (string) config('app.key'));
        abort_if(RateLimiter::tooManyAttempts($networkKey, 10), 429);
        RateLimiter::hit($networkKey, 3600);
        [$rated, $count] = DB::transaction(function () use ($post, $visitorHash, $request): array {
            Post::query()->whereKey($post->id)->lockForUpdate()->first();
            $rating = $post->ratings()->where('visitor_hash', $visitorHash)->first();

            if ($rating) {
                abort_unless(app(EngagementSettings::class)->module('post')['allow_change_vote'], 403);
                $rating->delete();
                $rated = false;
            } else {
                $post->ratings()->create([
                    'visitor_hash' => $visitorHash,
                    'ip_hash' => $request->ip()
                        ? hash('sha256', $request->ip().config('app.key'))
                        : null,
                ]);
                $rated = true;
            }

            return [$rated, $post->ratings()->count()];
        });
        $cookie = cookie(
            'blog_visitor',
            $visitorId,
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        );

        if ($request->expectsJson()) {
            return response()->json(['rated' => $rated, 'count' => $count])->withCookie($cookie);
        }

        return back()
            ->with('rating_success', __('noros-cms::rating.saved'))
            ->withCookie($cookie);
    }
}
