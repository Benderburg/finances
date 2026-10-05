@if($widget = $portfolioWidgets->get($data['widget_id'] ?? null))
<section id="{{ $blockId }}"><p>{{ $widget->eyebrow }}</p><h2>{{ $widget->title }}</h2><p>{{ $widget->description }}</p><div class="cards">
@foreach($widget->projects ?? [] as $project)<article>
@if($image = app(\Noros\Core\Support\MediaUrl::class)->resolve($project['image'] ?? null))<img src="{{ $image }}" alt="{{ $project['image_alt'] ?? $project['title'] ?? '' }}">@endif
<h3><a href="{{ app(\Noros\Core\Support\LocalUrl::class)->resolve($project['url'] ?? null) }}" @if($project['open_new_tab'] ?? false) target="_blank" rel="noopener noreferrer" @endif>{{ $project['title'] ?? '' }}</a></h3><p>{{ $project['description'] ?? '' }}</p>
</article>@endforeach</div></section>@endif
