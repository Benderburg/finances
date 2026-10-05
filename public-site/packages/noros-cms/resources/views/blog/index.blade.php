@extends('noros-cms::layout')
@php($seo = app(\Noros\Cms\Support\ContentSeo::class)->forBlogIndex())
@section('title', $seo->title)
@section('head')
@include('noros-core::components.seo', ['seo' => $seo, 'includeTitle' => false])
@endsection
@section('content')
<h1>{{ __('navigation.blog') }}</h1>
<form><input name="q" value="{{ $search }}" aria-label="{{ __('forms.search') }}"><button>{{ __('forms.search') }}</button></form>
<div class="cards">@foreach($posts as $post)<article><h2><a href="{{ route('blog.show', ['slug' => $post->slug]) }}">{{ $post->title }}</a></h2><p>{{ $post->excerpt }}</p></article>@endforeach</div>
{{ $posts->links() }}
@endsection
