<section id="{{ $blockId }}"><p>{{ $data['eyebrow'] ?? '' }}</p><h1>{{ $data['title'] ?? '' }}</h1><p>{{ $data['description'] ?? '' }}</p>
@if($image = app(\Noros\Core\Support\MediaUrl::class)->resolve($data['image'] ?? null))<img src="{{ $image }}" alt="{{ $data['title'] ?? '' }}">@endif
@if(filled($data['button_text'] ?? null))<a href="{{ app(\Noros\Core\Support\LocalUrl::class)->resolve($data['button_url'] ?? '#') }}">{{ $data['button_text'] }}</a>@endif</section>
