@extends('layouts.site')
@php($seo = app(\Noros\Cms\Support\ContentSeo::class)->forBlogIndex(__('site.blogTitle'), __('site.blogDescription')))
@section('content')
<header class="page-intro wrap"><p class="eyebrow">{{ __('site.blogLabel') }}</p><h1>{{ __('site.blogHeading') }}</h1><p class="lead">{{ __('site.blogDescription') }}</p></header>
<section class="wrap blog-list">@include('components.posts', ['articles' => $posts])<div class="pagination">{{ $posts->links() }}</div></section>
@include('blocks.cta', ['data' => ['title' => __('site.ctaTitle'), 'description' => __('site.ctaDescription')]])
@endsection
