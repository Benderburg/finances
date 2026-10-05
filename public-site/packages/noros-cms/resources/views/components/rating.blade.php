@if ($record->allowsRatings())
    @php
        $ratingLabels = collect(['title', 'choice', 'average', 'empty', 'saved', 'saving', 'error'])
            ->mapWithKeys(fn (string $key): array => [$key => __('noros-cms::rating.'.$key)])->all();
        $canVote = auth()->check() || app(\Noros\Cms\Support\EngagementSettings::class)->permitsGuest($type, 'ratings');
        $ratingLabels['login_required'] = __('noros-cms::engagement.login_required');
    @endphp
    <div data-noros-rating
        data-type="{{ $type }}"
        data-id="{{ $record->getKey() }}"
        data-url="{{ route('engagement.ratings.store', ['locale' => app()->getLocale(), 'type' => $type, 'id' => $record->getKey()]) }}"
        data-csrf="{{ csrf_token() }}"
        data-average="{{ $record->averageRating() }}"
        data-count="{{ $record->ratingCount() }}"
        data-locale="{{ app()->getLocale() }}"
        data-can-vote="{{ $canVote ? 'true' : 'false' }}"
        data-labels="{{ json_encode($ratingLabels, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) }}">
        <noscript>{{ __('noros-cms::rating.javascript') }}</noscript>
    </div>
    @once('noros-engagement-assets')
        <link rel="stylesheet" href="{{ asset('vendor/noros-cms/rating.css') }}">
        <script type="module" src="{{ asset('vendor/noros-cms/rating.js') }}"></script>
    @endonce
@endif
