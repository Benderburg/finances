@if(($data['enabled'] ?? true) !== false)
<section><h2>{{ $data['title'] ?? '' }}</h2><p>{{ $data['text'] ?? '' }}</p><address>
@if(filled($data['email'] ?? null))<p><a href="mailto:{{ $data['email'] }}">{{ $data['email'] }}</a></p>@endif
@if(filled($data['phone'] ?? null))<p><a href="tel:{{ preg_replace('/[^+0-9]/', '', $data['phone']) }}">{{ $data['phone'] }}</a></p>@endif
<p style="white-space:pre-line">{{ $data['address'] ?? '' }}</p></address></section>
@endif
