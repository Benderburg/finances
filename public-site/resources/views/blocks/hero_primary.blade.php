<section class="hero wrap">
    <div class="hero-copy"><p class="eyebrow"><span class="tiny-dot"></span>{{ $data['eyebrow'] }}</p><h1>{{ $data['title'] }}</h1><p class="lead">{{ $data['description'] }}</p>
    <div class="hero-actions"><a class="button primary" href="{{ url(app(\Noros\Core\Support\Settings::class)->get('site.register_url', config('site.register_url'))) }}">{{ __('site.start') }} <span aria-hidden="true">↗</span></a><a class="text-link" href="{{ route('pages.show', ['path' => 'features']) }}">{{ __('site.explore') }} <span aria-hidden="true">→</span></a></div>
    <p class="hero-note"><span aria-hidden="true">✓</span>{{ __('site.noBank') }}</p>
    <div class="hero-tags"><span>MDL</span><span>EUR</span><span>USD</span><span>RON</span><small>{{ __('site.yourCurrencies') }}</small></div></div>
    <div class="hero-visual">@include('components.preview', ['kind' => 'dashboard'])<div class="floating-note"><span class="mini-symbol">↗</span><div><small>{{ __('site.savedVacation') }}</small><strong>€640 <span>/ €1 500</span></strong></div><span class="note-check">✓</span></div></div>
</section>
<div class="intro-line wrap"><span>{{ __('site.smallSteps') }}</span><span>{{ __('site.clearPicture') }}</span><span>{{ __('site.ownPace') }}</span></div>
