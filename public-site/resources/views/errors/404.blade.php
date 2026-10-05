@extends('layouts.site')
@php($seo = new \Noros\Core\Support\Seo(title: __('site.notFoundTitle'), robots: 'noindex, follow'))
@section('content')
<section class="wrap not-found"><span class="eyebrow">404</span><h1>{{ __('site.notFound') }}</h1><p class="lead">{{ __('site.notFoundText') }}</p><a class="button primary" href="{{ route('home') }}">{{ __('site.backHome') }} →</a></section>
@endsection
