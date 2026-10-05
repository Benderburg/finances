@if(($data['enabled'] ?? true) !== false)
<section><h2>{{ $data['title'] ?? '' }}</h2><div class="cards">
@foreach($data['items'] ?? [] as $item)<figure>
@if($image = app(\Noros\Core\Support\MediaUrl::class)->resolve($item['image'] ?? null))<img src="{{ $image }}" alt="{{ $item['alt'] ?? '' }}" loading="lazy">@endif
<figcaption>{{ $item['caption'] ?? '' }}</figcaption></figure>@endforeach
</div></section>
@endif
