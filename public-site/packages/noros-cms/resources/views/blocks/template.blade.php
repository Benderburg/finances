@php($templateView = app(\Noros\Cms\Support\BlockRegistry::class)->templateView($data['template'] ?? ''))
@if($templateView) @include($templateView) @endif
