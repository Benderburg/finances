@foreach($data['slides'] ?? [] as $slide)@include('noros-cms::blocks.hero_primary', ['data' => $slide, 'blockId' => $blockId.'-'.$loop->index])@endforeach
