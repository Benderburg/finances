@props(['seo', 'includeTitle' => true])
@if ($includeTitle)<title>{{ $seo->title }}</title>@endif
@if ($seo->description !== null)
    <meta name="description" content="{{ $seo->description }}">
@endif
@if ($seo->ogDescription !== null || $seo->description !== null)
    <meta property="og:description" content="{{ $seo->ogDescription ?? $seo->description }}">
@endif
<meta name="robots" content="{{ $seo->robots }}">
<meta property="og:title" content="{{ $seo->ogTitle ?? $seo->title }}">
<meta property="og:type" content="{{ $seo->type }}">
@if ($seo->siteName)<meta property="og:site_name" content="{{ $seo->siteName }}">@endif
@if ($seo->locale)<meta property="og:locale" content="{{ $seo->locale }}">@endif
<meta name="twitter:card" content="{{ $seo->twitterCard }}">
<meta name="twitter:title" content="{{ $seo->ogTitle ?? $seo->title }}">
@if ($seo->ogDescription !== null || $seo->description !== null)
    <meta name="twitter:description" content="{{ $seo->ogDescription ?? $seo->description }}">
@endif
@if ($seo->canonical !== null)
    <link rel="canonical" href="{{ $seo->canonical }}">
    <meta property="og:url" content="{{ $seo->canonical }}">
@endif
@if ($seo->image !== null)
    <meta property="og:image" content="{{ $seo->image }}">
    <meta name="twitter:image" content="{{ $seo->image }}">
@endif
@foreach ($seo->alternates as $locale => $url)
    <link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}">
@endforeach
@if ($seo->jsonLd !== [])
    <script type="application/ld+json">{!! $seo->jsonLdHtml() !!}</script>
@endif
