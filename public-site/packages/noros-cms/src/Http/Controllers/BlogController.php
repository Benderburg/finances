<?php

namespace Noros\Cms\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Noros\Cms\Models\Category;
use Noros\Cms\Models\Post;
use Noros\Cms\Models\Tag;
use Noros\Cms\Support\BlogSidebarData;
use Noros\Cms\Support\LocalizedContent;
use Symfony\Component\HttpFoundation\Response;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $activeCategory = filled($filters['category'] ?? null)
            ? app(LocalizedContent::class)->findTaxonomy(Category::class, $filters['category'])
            : null;
        $activeTag = filled($filters['tag'] ?? null)
            ? app(LocalizedContent::class)->findTaxonomy(Tag::class, $filters['tag'])
            : null;

        $posts = Post::query()
            ->published()
            ->with(['author', 'category', 'tags'])
            ->withCount(['approvedComments', 'ratings'])
            ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim($filters['q']);
                $query->search($search);
            })
            ->when($activeCategory, fn (Builder $query): Builder => $query->whereBelongsTo($activeCategory))
            ->when($activeTag, fn (Builder $query): Builder => $query->whereHas(
                'tags',
                fn (Builder $tagQuery): Builder => $tagQuery->whereKey($activeTag->getKey()),
            ))
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->paginate(6)
            ->withQueryString();
        app(LocalizedContent::class)->warm($posts->getCollection());

        return view(config('noros-cms.views.blog_index'), [
            'posts' => $posts,
            'activeCategory' => $activeCategory,
            'activeTag' => $activeTag,
            'search' => $filters['q'] ?? '',
        ] + $this->sidebarData());
    }

    public function show(Request $request, string $slug): Response
    {
        $request->validate(['comments_page' => ['nullable', 'integer', 'between:1,100000']]);
        $content = app(LocalizedContent::class);
        $post = $content->find(Post::class, $slug);
        if (! $post) {
            $redirect = $content->redirect(Post::class, $slug, app()->getLocale());
            if ($redirect) {
                return redirect()->route('blog.show', ['slug' => $content->path($redirect)], 301);
            }
            abort(404);
        }
        $post->load(['author', 'category', 'tags'])->loadCount(['approvedComments', 'ratings']);
        $content->warm([$post]);

        $viewedPosts = $request->session()->get('viewed_blog_posts', []);
        if (! in_array($post->id, $viewedPosts, true)) {
            Post::withoutTimestamps(fn () => $post->increment('views_count'));
            $request->session()->put('viewed_blog_posts', [...$viewedPosts, $post->id]);
        }

        $comments = $post->comments()
            ->approved()
            ->whereNull('parent_id')
            ->with('rating')
            ->oldest('id')
            ->paginate(10, ['*'], 'comments_page');

        $tagIds = $post->tags->modelKeys();
        $relatedPosts = Post::query()
            ->published()
            ->whereKeyNot($post->id)
            ->with(['author', 'category'])
            ->withCount(['approvedComments', 'ratings'])
            ->where(function (Builder $query) use ($post, $tagIds): void {
                if ($post->category_id) {
                    $query->where('category_id', $post->category_id);
                }

                if ($tagIds !== []) {
                    $method = $post->category_id ? 'orWhereHas' : 'whereHas';
                    $query->{$method}('tags', fn (Builder $tagQuery): Builder => $tagQuery->whereKey($tagIds));
                }
            })
            ->orderByDesc('published_at')
            ->limit(2)
            ->get();

        $content->warm($relatedPosts);

        $visitorId = $request->session()->get('noros.blog_visitor') ?? $request->cookie('blog_visitor');
        if (! is_string($visitorId) || ! Str::isUuid($visitorId)) {
            $visitorId = (string) Str::uuid();
        }
        $request->session()->put('noros.blog_visitor', $visitorId);
        $visitorHash = $request->user() ? hash_hmac('sha256', 'user:'.$request->user()->getAuthIdentifier(), (string) config('app.key')) : hash('sha256', $visitorId.config('app.key'));
        $rated = $post->ratings()->where('visitor_hash', $visitorHash)->exists();

        $response = response()->view(config('noros-cms.views.blog_show'), [
            'post' => $post,
            'comments' => $comments,
            'relatedPosts' => $relatedPosts,
            'rated' => $rated,
        ] + $this->sidebarData($post));

        if (! $request->hasCookie('blog_visitor')) {
            $response->withCookie(cookie(
                'blog_visitor',
                $visitorId,
                60 * 24 * 365,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax',
            ));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function sidebarData(?Post $currentPost = null): array
    {
        return app(BlogSidebarData::class)->get($currentPost);
    }
}
