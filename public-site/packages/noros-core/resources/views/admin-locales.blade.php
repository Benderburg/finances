<nav aria-label="Language" style="display:flex;gap:.5rem;padding:.5rem">
@foreach(config('noros.locales') as $locale => $label)<a href="{{ request()->fullUrlWithQuery(['locale' => $locale]) }}" lang="{{ $locale }}" @if(app()->getLocale() === $locale) aria-current="true" @endif>{{ strtoupper($locale) }}</a>@endforeach
</nav>
