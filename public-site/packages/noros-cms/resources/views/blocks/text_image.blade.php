@if(($data['enabled'] ?? true) !== false)
<section><h2>{{ $data['title'] ?? '' }}</h2>
@if($image = app(\Noros\Core\Support\MediaUrl::class)->resolve($data['image'] ?? null))<img src="{{ $image }}" alt="{{ $data['alt'] ?? '' }}">@endif
<p style="white-space:pre-line">{{ $data['text'] ?? '' }}</p></section>
@endif
