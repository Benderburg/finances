@foreach($items as $item)
@if($item->is_active && $item->is_available)
<li><a href="{{ $item->resolved_url }}" @if($item->target === '_blank') target="_blank" rel="noopener noreferrer" @endif>{{ $item->label }}</a>
@if($depth < 10 && $item->children->isNotEmpty())<ul>@include('noros-cms::menu-items', ['items' => $item->children, 'depth' => $depth + 1])</ul>@endif</li>
@endif
@endforeach
