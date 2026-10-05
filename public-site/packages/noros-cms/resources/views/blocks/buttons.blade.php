@if(($data['enabled'] ?? true) !== false)
<section><h2>{{ $data['title'] ?? '' }}</h2><nav aria-label="{{ $data['title'] ?? __('noros-cms::blocks.buttons') }}">
@foreach($data['items'] ?? [] as $item)<a href="{{ app(\Noros\Core\Support\LocalUrl::class)->resolve($item['url'] ?? '#') }}">{{ $item['label'] ?? '' }}</a> @endforeach
</nav></section>
@endif
