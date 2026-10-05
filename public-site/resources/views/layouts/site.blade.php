<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    @include('noros-core::components.seo', ['seo' => $seo])
    <meta name="theme-color" content="#3659e3">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/favicon.svg') }}?v=logo-1">
    <link rel="icon" sizes="16x16 32x32 48x48" href="{{ asset('favicon.ico') }}?v=logo-1">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}?v=logo-1">
    <link rel="preload" href="{{ asset('site/manrope.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('site/site.css') }}?v=logo-1">
    <script src="{{ asset('site/site.js') }}?v=1" defer></script>
</head>
<body>
<a class="skip" href="#main">{{ __('site.skip') }}</a>
<header class="site-header wrap">
    <a class="brand" href="{{ route('home') }}" aria-label="{{ $site['name'] }}"><img class="brand-logo" src="{{ asset('icons/logo.svg') }}" width="1040" height="280" alt="{{ $site['name'] }}"></a>
    <nav class="desktop-nav" aria-label="{{ __('site.navigation') }}">
        @include('components.menu', ['menu' => $menus->get('header')])
    </nav>
    <div class="header-actions">
        @include('components.languages')
        <a class="login" href="{{ url($site['login_url']) }}">{{ __('site.login') }}</a>
        <a class="button primary small" href="{{ url($site['register_url']) }}">{{ __('site.start') }}<span aria-hidden="true">↗</span></a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="{{ __('site.menu') }}"><span></span><span></span></button>
    </div>
</header>
<nav id="mobile-menu" class="mobile-menu wrap" aria-label="{{ __('site.navigation') }}" hidden>
    @include('components.menu', ['menu' => $menus->get('header')])
    <a href="{{ url($site['login_url']) }}">{{ __('site.login') }}</a>
</nav>
<main id="main">@yield('content')</main>
<footer class="site-footer wrap">
    <div class="footer-top"><div><a class="brand" href="{{ route('home') }}"><img class="brand-logo" src="{{ asset('icons/logo.svg') }}" width="1040" height="280" alt="{{ $site['name'] }}"></a><p>{{ __('site.footer') }}</p></div>
    <nav aria-label="{{ __('site.footerNav') }}">@include('components.menu', ['menu' => $menus->get('footer')])</nav>
    <div class="footer-contact"><span class="eyebrow">{{ __('site.contact') }}</span><a href="mailto:{{ $site['contact_email'] }}">{{ $site['contact_email'] }} <span aria-hidden="true">↗</span></a></div></div>
    <div class="footer-bottom"><span>© {{ date('Y') }} {{ $site['name'] }}</span><a href="{{ $site['platform_url'] }}">{{ $site['platform_label'] }}</a><span>{{ __('site.made') }}</span></div>
</footer>
</body></html>
