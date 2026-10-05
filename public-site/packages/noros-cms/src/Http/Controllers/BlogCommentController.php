<?php

namespace Noros\Cms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Noros\Cms\Contracts\EngagementChallenge;
use Noros\Cms\Http\Requests\StoreBlogCommentRequest;
use Noros\Cms\Models\Post;
use Noros\Cms\Support\EngagementService;
use Noros\Cms\Support\LocalizedContent;

class BlogCommentController extends Controller
{
    public function store(StoreBlogCommentRequest $request, string $slug): RedirectResponse
    {
        $post = app(LocalizedContent::class)->find(Post::class, $slug);
        abort_unless($post !== null, 404);
        app(EngagementChallenge::class)->validate($request);
        app(EngagementService::class)->comment('post', $post->id, $request->validated(), $request->user()?->id);

        return to_route('blog.show', $post)
            ->with('comment_success', __('noros-cms::engagement.submitted'));
    }
}
