@php
    $languages = isset($contentModel) ? app(\Noros\Cms\Support\ContentSeo::class)->urlsForModel($contentModel) : collect(config('noros.locales'))->mapWithKeys(fn ($label, $locale) => [$locale => request()->routeIs('blog.index') ? route('blog.index', ['locale' => $locale]) : route('home', ['locale' => $locale])])->all();
@endphp
<details class="languages"><summary aria-label="{{ __('site.language') }}">{{ strtoupper(app()->getLocale()) }} <span aria-hidden="true">⌄</span></summary><div>
@foreach($languages as $locale => $url)<a href="{{ $url }}" lang="{{ $locale }}" hreflang="{{ $locale }}" @if($locale === app()->getLocale()) aria-current="true" @endif>{{ config('noros.locales.'.$locale) }}</a>@endforeach
</div></details>
