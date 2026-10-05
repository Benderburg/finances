<section class="product-block wrap {{ ($data['image_position'] ?? '') === 'left' ? 'reverse' : '' }}" @if(filled($data['anchor'] ?? null)) id="{{ $data['anchor'] }}" @endif>
    <div class="product-copy"><p class="eyebrow">{{ $data['eyebrow'] }}</p><h2>{{ $data['title'] }}</h2><p class="lead">{{ $data['description'] }}</p>
    @if(filled($data['items'] ?? []))<ul class="check-list">@foreach($data['items'] as $item)<li><span aria-hidden="true">✓</span>{{ $item['text'] }}</li>@endforeach</ul>@endif
    </div><div class="product-visual visual-{{ $data['image'] }}">@include('components.preview', ['kind' => $data['image']])</div>
</section>
