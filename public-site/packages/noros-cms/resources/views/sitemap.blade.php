@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($entries as $entry)
    <url>
        <loc>{{ $entry['url'] }}</loc>
        @if ($entry['updated_at'])
            <lastmod>{{ $entry['updated_at']->toAtomString() }}</lastmod>
        @endif
    </url>
@endforeach
</urlset>
