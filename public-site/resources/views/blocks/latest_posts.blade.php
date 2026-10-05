@php($latestPosts = \Noros\Cms\Models\Post::query()->published()->latest('published_at')->limit(max(1, min(12, (int) ($data['limit'] ?? 3))))->get())
<section class="blog-section wrap"><div class="section-heading"><div><p class="eyebrow">{{ __('site.blogLabel') }}</p><h2>{{ $data['title'] }}</h2></div><a class="text-link" href="{{ route('blog.index') }}">{{ __('site.allPosts') }} <span aria-hidden="true">→</span></a></div>
@include('components.posts', ['articles' => $latestPosts])</section>
