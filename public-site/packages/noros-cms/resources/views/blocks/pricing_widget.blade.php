@if($widget = $pricingWidgets->get($data['widget_id'] ?? null))
<section id="{{ $blockId }}"><p>{{ $widget->eyebrow }}</p><h2>{{ $widget->title }}</h2><p>{{ $widget->description }}</p><div class="cards">
@foreach($widget->plans ?? [] as $plan)<article><h3>{{ $plan['name'] ?? '' }}</h3><p>{{ $plan['subtitle'] ?? '' }}</p>
<p>{{ $plan['price_prefix'] ?? '' }} {{ $plan['price'] ?? '' }} {{ $widget->currency }} {{ $plan['price_suffix'] ?? '' }}</p><p>{{ $plan['timeline'] ?? '' }}</p>
<ul>@foreach($plan['features'] ?? [] as $feature)<li>{{ $feature }}</li>@endforeach</ul>
@if(filled($plan['button_text'] ?? null))<a href="{{ app(\Noros\Core\Support\LocalUrl::class)->resolve($plan['button_url'] ?? null) }}">{{ $plan['button_text'] }}</a>@endif
</article>@endforeach</div>
@if($widget->tasks)<table>@foreach($widget->tasks as $task)<tr><td>{{ $task['name'] ?? '' }}</td><td>{{ $task['timeline'] ?? '' }}</td><td>{{ $task['price'] ?? '' }} {{ $widget->currency }}</td></tr>@endforeach</table>@endif
</section>@endif
