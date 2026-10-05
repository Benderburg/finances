@extends('noros-cms::layout')
@php($contentModel = $page)
@section('title', $page->seo_title ?: $page->title)
@section('head')
@include('noros-core::components.seo', ['seo' => app(\Noros\Cms\Support\ContentSeo::class)->forModel($page), 'includeTitle' => false])
@endsection
@section('content')
@if($page->template !== \Noros\Cms\Enums\PageTemplate::Landing)<h1>{{ $page->heading ?: $page->title }}</h1>@endif
@foreach($page->blocks ?? [] as $block)
    @php($blockView = app(\Noros\Cms\Support\BlockRegistry::class)->view($block['type'] ?? ''))
    @php($blockData = app(\Noros\Cms\Support\BlockRegistry::class)->localizedData($block['data'] ?? []))
    @if($blockView && ($block['data']['enabled'] ?? true) && ($blockData['enabled'] ?? true)) @include($blockView, ['data' => $blockData, 'blockId' => 'block-'.$loop->index]) @endif
@endforeach
@if($page->template === \Noros\Cms\Enums\PageTemplate::Sidebar && filled($page->sidebar_content))
<aside>{!! \Noros\Core\Support\SafeHtml::clean($page->sidebar_content) !!}</aside>
@endif
@endsection
