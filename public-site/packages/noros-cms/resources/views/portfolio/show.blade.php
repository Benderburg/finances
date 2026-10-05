@extends('noros-cms::layout')
@php($contentModel = $project)
@section('title', $project->seo_title ?: $project->title)
@section('head')@include('noros-core::components.seo', ['seo' => app(\Noros\Cms\Support\ContentSeo::class)->forModel($project), 'includeTitle' => false])@endsection
@section('content')<article><h1>{{ $project->heading ?: $project->title }}</h1>
@if($project->image)<img src="{{ app(\Noros\Core\Support\MediaUrl::class)->resolve($project->image) }}" alt="{{ $project->title }}">@endif
<p>{{ $project->summary }}</p>
<dl>
@foreach(['client', 'year'] as $field)@if(filled($project->{$field}))<dt>{{ __('noros-cms::blocks.'.$field) }}</dt><dd>{{ $project->{$field} }}</dd>@endif @endforeach
@foreach(['tags', 'technologies'] as $field)@if(filled($project->{$field}))<dt>{{ __('noros-cms::blocks.'.$field) }}</dt><dd>{{ implode(', ', $project->{$field}) }}</dd>@endif @endforeach
</dl>
@if($project->project_url)<p><a href="{{ app(\Noros\Core\Support\LocalUrl::class)->resolve($project->project_url) }}" rel="noopener noreferrer">{{ __('noros-cms::blocks.project_url') }}</a></p>@endif
@foreach($project->content ?? [] as $paragraph)<p>{{ $paragraph }}</p>@endforeach
@foreach(['task', 'solution', 'result'] as $field)@if(filled($project->{$field}))<section><h2>{{ __('noros-cms::blocks.'.$field) }}</h2><p style="white-space:pre-line">{{ $project->{$field} }}</p></section>@endif @endforeach
@if($project->gallery)<section class="cards">@foreach($project->gallery as $image)@if($imageUrl = app(\Noros\Core\Support\MediaUrl::class)->resolve($image))<img src="{{ $imageUrl }}" alt="{{ $project->title }}" loading="lazy">@endif @endforeach</section>@endif
</article>
@if($relatedCases ?? [])<section><h2>{{ __('noros-cms::blocks.related') }}</h2>@foreach($relatedCases as $related)<a href="{{ $related['url'] }}">{{ $related['title'] }}</a> @endforeach</section>@endif
@endsection
