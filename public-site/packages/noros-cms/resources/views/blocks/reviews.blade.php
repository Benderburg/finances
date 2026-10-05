@if(($data['enabled'] ?? true) !== false)
<section><h2>{{ $data['title'] ?? '' }}</h2><div class="cards">
@foreach($data['items'] ?? [] as $item)<figure><blockquote>{{ $item['quote'] ?? '' }}</blockquote><figcaption>{{ $item['name'] ?? '' }} — {{ $item['role'] ?? '' }}</figcaption></figure>@endforeach
</div></section>
@endif
