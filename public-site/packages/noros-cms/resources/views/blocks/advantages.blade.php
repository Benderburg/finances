@if(($data['enabled'] ?? true) !== false)
<section><h2>{{ $data['title'] ?? '' }}</h2><div class="cards">
@foreach($data['items'] ?? [] as $item)<article>
@if($image = app(\Noros\Core\Support\MediaUrl::class)->resolve($item['image'] ?? null))<img src="{{ $image }}" alt="{{ $item['alt'] ?? '' }}" loading="lazy">@endif
<h3>{{ $item['title'] ?? '' }}</h3><p>{{ $item['text'] ?? '' }}</p>
@if(filled($item['url'] ?? null))<a href="{{ app(\Noros\Core\Support\LocalUrl::class)->resolve($item['url']) }}">{{ __('noros-cms::blocks.read_more') }}</a>@endif
</article>@endforeach</div></section>
@endif
