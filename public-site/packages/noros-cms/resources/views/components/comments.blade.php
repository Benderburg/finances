@if(config('noros-cms.features.comments'))
    @php
        $settings = app(\Noros\Cms\Support\EngagementSettings::class);
        $loginRequired = !auth()->check() && !$settings->permitsGuest($type, 'comments');
        $canSubmit = $record->allowsComments() && !$loginRequired;
        $allowScore = $type === 'product' && $record->allowsRatings() && (auth()->check() || $settings->permitsGuest($type, 'ratings'));
        $publicComments = $type === 'post' ? $record->comments() : $record->engagementComments();
        $publicComments = $comments ?? $publicComments->approved()->whereNull('parent_id')->with('rating')->oldest('id')->paginate(10, ['*'], 'comments_page');
        $initial = ['data' => $publicComments->map(fn ($comment) => ['id' => $comment->id, 'author' => $comment->author_name, 'body' => $comment->body, 'score' => $comment->rating?->score, 'created_at' => $comment->created_at->toIso8601String()]), 'page' => $publicComments->currentPage(), 'last_page' => $publicComments->lastPage()];
        $labels = collect(['reviews', 'comments', 'name', 'email', 'body', 'score', 'consent', 'send', 'reply', 'replies', 'cancel', 'previous', 'next', 'empty', 'loading', 'error', 'submitted', 'optional_score', 'login_required', 'closed'])->mapWithKeys(fn ($key) => [$key => __('noros-cms::engagement.'.$key)])->all();
        $labels['title'] = $labels[$type === 'product' ? 'reviews' : 'comments'];
    @endphp
    <div id="comment-form" data-noros-comments
        data-url="{{ route('engagement.comments.index', ['locale' => app()->getLocale(), 'type' => $type, 'id' => $record->getKey()]) }}"
        data-csrf="{{ csrf_token() }}" data-locale="{{ app()->getLocale() }}"
        data-can-submit="{{ $canSubmit ? 'true' : 'false' }}" data-login-required="{{ $loginRequired ? 'true' : 'false' }}" data-allow-score="{{ $allowScore ? 'true' : 'false' }}"
        data-initial="{{ json_encode($initial, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) }}"
        data-labels="{{ json_encode($labels, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) }}">
        <h2>{{ __('noros-cms::engagement.'.($type === 'product' ? 'reviews' : 'comments')) }}</h2>
        @foreach($publicComments as $comment)
            <article><strong>{{ $comment->author_name }}</strong><p>{{ $comment->body }}</p></article>
        @endforeach
        <noscript>{{ __('noros-cms::engagement.javascript') }}</noscript>
    </div>
    @once('noros-engagement-assets')
        <link rel="stylesheet" href="{{ asset('vendor/noros-cms/rating.css') }}">
        <script type="module" src="{{ asset('vendor/noros-cms/rating.js') }}"></script>
    @endonce
@endif
