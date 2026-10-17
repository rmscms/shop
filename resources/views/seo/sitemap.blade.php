{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach($entries as $entry)
  <url>
    <loc>{{ $entry['loc'] }}</loc>
@if($entry['lastmod'])
    <lastmod>{{ $entry['lastmod']->toAtomString() }}</lastmod>
@endif
@foreach($entry['images'] as $image)
    <image:image><image:loc>{{ $image }}</image:loc></image:image>
@endforeach
  </url>
@endforeach
</urlset>
