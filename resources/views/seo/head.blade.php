{{-- Usage in a storefront layout: @include('shop::seo.head', ['seo' => app(\RMS\Shop\Services\SeoService::class)->forProduct($product)]) --}}
<title>{{ $seo['title'] }}</title>
@if(!empty($seo['description']))
<meta name="description" content="{{ $seo['description'] }}">
@endif
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta name="robots" content="{{ $seo['robots'] }}">
@foreach($seo['open_graph'] ?? [] as $property => $content)
<meta property="{{ $property }}" content="{{ $content }}">
@endforeach
@foreach($seo['twitter'] ?? [] as $name => $content)
<meta name="{{ $name }}" content="{{ $content }}">
@endforeach
@foreach($seo['json_ld'] ?? [] as $schema)
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endforeach
