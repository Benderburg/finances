<section><h2>{{ $data['title'] ?? '' }}</h2><p>{{ $data['description'] ?? '' }}</p>{!! \Noros\Core\Support\SafeHtml::clean($data['content'] ?? '') !!}</section>
