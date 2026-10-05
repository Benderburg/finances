@if(($data['enabled'] ?? true) !== false && config('noros-cms.features.blog'))
@php($latestPosts = \Noros\Cms\Models\Post::query()->published()->latest('published_at')->limit(max(1, min(12, (int) ($data['limit'] ?? 3))))->get())
<section><h2>{{ $data['title'] ?? __('noros-cms::blocks.latest_posts') }}</h2><div class="cards">
@foreach($latestPosts as $article)<article><h3><a href="{{ route('blog.show', ['slug' => app(\Noros\Cms\Support\LocalizedContent::class)->path($article)]) }}">{{ $article->translated('title') }}</a></h3><p>{{ $article->translated('excerpt') }}</p></article>@endforeach
</div></section>
@endif
