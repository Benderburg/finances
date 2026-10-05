@if(($data['enabled'] ?? true) !== false)
<section><h2>{{ $data['title'] ?? '' }}</h2><div class="cards">
@foreach($data['items'] ?? [] as $item)<article>
@if($image = app(\Noros\Core\Support\MediaUrl::class)->resolve($item['image'] ?? null))<img src="{{ $image }}" alt="{{ $item['alt'] ?? $item['name'] ?? '' }}" loading="lazy">@endif
@if(filled($item['url'] ?? null))<a href="{{ app(\Noros\Core\Support\LocalUrl::class)->resolve($item['url']) }}">{{ $item['name'] ?? '' }}</a>@else<p>{{ $item['name'] ?? '' }}</p>@endif
</article>@endforeach</div></section>
@endif
