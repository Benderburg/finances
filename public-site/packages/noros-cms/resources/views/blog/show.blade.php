@extends('noros-cms::layout')
@php($contentModel = $post)
@section('title', $post->seo_title ?: $post->title)
@section('head')@include('noros-core::components.seo', ['seo' => app(\Noros\Cms\Support\ContentSeo::class)->forModel($post), 'includeTitle' => false])@endsection
@section('content')
<article><h1>{{ $post->title }}</h1><p>{{ $post->author?->name }}</p>{!! \Noros\Core\Support\SafeHtml::clean($post->content) !!}</article>
@include('noros-cms::components.rating', ['type' => 'post', 'record' => $post])
@include('noros-cms::components.comments', ['type' => 'post', 'record' => $post])
@endsection
