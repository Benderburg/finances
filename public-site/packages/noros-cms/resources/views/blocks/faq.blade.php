@if(($data['enabled'] ?? true) !== false)
<section><h2>{{ $data['title'] ?? '' }}</h2>
@foreach($data['items'] ?? [] as $item)<details><summary>{{ $item['question'] ?? '' }}</summary><p style="white-space:pre-line">{{ $item['answer'] ?? '' }}</p></details>@endforeach
</section>
@endif
