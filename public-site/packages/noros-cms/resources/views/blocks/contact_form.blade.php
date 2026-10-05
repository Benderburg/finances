@if(($data['enabled'] ?? true) !== false && config('noros-cms.contact.enabled') && filter_var(config('noros-cms.contact.recipient'), FILTER_VALIDATE_EMAIL))
<section><h2>{{ $data['title'] ?? __('noros-cms::blocks.contact_form') }}</h2><p>{{ $data['text'] ?? '' }}</p>
@if(session('noros-contact-sent'))<p role="status">{{ session('noros-contact-sent') }}</p>@endif
@if($errors->any())<ul role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<form method="post" action="{{ route('noros-cms.contact.store', ['locale' => app()->getLocale()]) }}">@csrf
<label>{{ __('noros-cms::blocks.name') }} <input name="name" value="{{ old('name') }}" maxlength="100" required autocomplete="name"></label>
<label>{{ __('noros-cms::blocks.email') }} <input name="email" type="email" value="{{ old('email') }}" maxlength="255" required autocomplete="email"></label>
<label>{{ __('noros-cms::blocks.message') }} <textarea name="message" minlength="5" maxlength="10000" required>{{ old('message') }}</textarea></label>
<input name="company" value="" tabindex="-1" autocomplete="off" hidden>
<label><input name="consent" type="checkbox" value="1" required> {{ __('noros-cms::blocks.consent') }}</label>
<button type="submit">{{ __('noros-cms::blocks.send') }}</button></form></section>
@endif
