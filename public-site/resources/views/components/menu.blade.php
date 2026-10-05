@foreach($menu?->rootItems ?? [] as $item)
    @if($item->is_available)<a href="{{ $item->resolved_url }}" @if($item->target === '_blank') target="_blank" rel="noopener noreferrer" @endif>{{ $item->label }}</a>@endif
@endforeach
