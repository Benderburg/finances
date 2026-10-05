@extends('layouts.site')
@php
    $contentModel = $page;
    $seo = app(\Noros\Cms\Support\ContentSeo::class)->forModel($page);
@endphp
@section('content')
@if($page->template !== \Noros\Cms\Enums\PageTemplate::Landing)
<header class="page-intro wrap narrow"><p class="eyebrow">Norocel</p><h1>{{ $page->heading ?: $page->title }}</h1></header>
@endif
@foreach($page->blocks ?? [] as $block)
    @php
        $registry = app(\Noros\Cms\Support\BlockRegistry::class);
        $blockView = $registry->view($block['type'] ?? '');
        $data = $registry->localizedData($block['data'] ?? []);
    @endphp
    @if($blockView && ($data['enabled'] ?? true)) @include($blockView, ['data' => $data]) @endif
@endforeach
@endsection
