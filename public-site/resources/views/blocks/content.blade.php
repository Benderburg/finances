<section class="wrap {{ ($data['class'] ?? '') === 'brand-story' ? 'brand-story' : 'prose narrow' }}">{!! \Noros\Core\Support\SafeHtml::clean($data['content'] ?? '') !!}</section>
