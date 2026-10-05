@extends('layouts.site')
@php
    $contentModel = $post;
    $seo = app(\Noros\Cms\Support\ContentSeo::class)->forModel($post);
@endphp
@section('content')
<article class="article wrap narrow"><a class="text-link" href="{{ route('blog.index') }}">← {{ __('site.allPosts') }}</a><header><p class="eyebrow">{{ __('site.blogLabel') }} · <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j F Y') }}</time></p><h1>{{ $post->title }}</h1><p class="lead">{{ $post->excerpt }}</p></header><img class="article-cover" src="{{ app(\Noros\Core\Support\MediaUrl::class)->resolve($post->cover_image) }}" alt="" width="640" height="400"><div class="prose">{!! \Noros\Core\Support\SafeHtml::clean($post->content) !!}</div></article>
@if($relatedPosts->isNotEmpty())<section class="wrap blog-section"><h2>{{ __('site.moreReading') }}</h2>@include('components.posts', ['articles' => $relatedPosts])</section>@endif
@include('blocks.cta', ['data' => ['title' => __('site.ctaTitle'), 'description' => __('site.ctaDescription')]])
@endsection
