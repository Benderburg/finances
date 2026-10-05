@php
    $siteSettings = app(\Noros\Core\Support\Settings::class);
    $siteName = $siteSettings->get('site.name', config('app.name'));
    $homeRoute = \Illuminate\Support\Facades\Route::has('home') ? 'home' : (\Illuminate\Support\Facades\Route::has('blog.index') ? 'blog.index' : null);
    $siteMenus = app(\Noros\Cms\Support\SiteNavigation::class)->menus();
    $siteLogo = app(\Noros\Core\Support\MediaUrl::class)->resolve($siteSettings->get('site.logo'));
    $siteFavicon = app(\Noros\Core\Support\MediaUrl::class)->resolve($siteSettings->get('site.favicon'));
    $localeUrls = isset($contentModel) ? app(\Noros\Cms\Support\ContentSeo::class)->urlsForModel($contentModel) : [];
    $localeRoute = request()->routeIs('blog.index') ? 'blog.index' : $homeRoute;
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', e($siteName))</title>@if($siteFavicon)<link rel="icon" href="{{ $siteFavicon }}">@endif @yield('head')
<style>body{font:18px/1.6 system-ui;margin:0;color:#182536;background:#f6f8fb}header,main,footer{max-width:1100px;margin:auto;padding:24px}header{display:flex;gap:24px;align-items:center}nav{display:flex;gap:16px;flex-wrap:wrap}nav ul{list-style:none;padding:0;display:flex;gap:16px;flex-wrap:wrap}nav ul ul{display:block}a{color:#234d91}img{max-width:100%;height:auto}.site-logo{max-height:48px}article,section{margin:24px 0}input,textarea,select,button{font:inherit;padding:10px}button{cursor:pointer}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:24px}</style>
</head><body>
<header><a href="{{ $homeRoute ? route($homeRoute) : url('/'.app()->getLocale()) }}">@if($siteLogo)<img class="site-logo" src="{{ $siteLogo }}" alt="{{ $siteName }}">@else{{ $siteName }}@endif</a><nav>
@if($menu = $siteMenus->get('header'))<ul>@include('noros-cms::menu-items', ['items' => $menu->rootItems, 'depth' => 0])</ul>
@elseif(config('noros-cms.features.blog'))<a href="{{ route('blog.index') }}">{{ __('navigation.blog') }}</a>@endif
@foreach(config('noros.locales') as $locale => $label)<a hreflang="{{ $locale }}" href="{{ $localeUrls[$locale] ?? ($localeRoute ? route($localeRoute, ['locale' => $locale]) : url('/'.$locale)) }}">{{ $label }}</a>@endforeach
</nav></header>
<main>@yield('content')</main><footer><p>{{ $siteName }}</p><p>{{ $siteSettings->get('site.description') }}</p>
@foreach(['footer_primary', 'footer_secondary'] as $location)@if($menu = $siteMenus->get($location))<nav><ul>@include('noros-cms::menu-items', ['items' => $menu->rootItems, 'depth' => 0])</ul></nav>@endif@endforeach
@if($email = $siteSettings->get('site.email'))<a href="mailto:{{ $email }}">{{ $email }}</a>@endif
@if($phone = $siteSettings->get('site.phone'))<p>{{ $phone }}</p>@endif
@if($address = $siteSettings->get('site.address'))<p>{{ $address }}</p>@endif
<p>{{ $siteSettings->get('site.legal') }}</p></footer></body></html>
