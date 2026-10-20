User-agent: *
Allow: /
@foreach($disallow as $path)
Disallow: {{ $path }}
@endforeach

Sitemap: {{ $sitemap }}
