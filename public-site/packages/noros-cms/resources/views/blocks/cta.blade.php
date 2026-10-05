@if(($data['enabled'] ?? true) !== false)
<section><h2>{{ $data['title'] ?? '' }}</h2><p>{{ $data['text'] ?? '' }}</p><a href="{{ app(\Noros\Core\Support\LocalUrl::class)->resolve($data['url'] ?? '#') }}">{{ $data['label'] ?? '' }}</a></section>
@endif
