<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentationTitle }}</title>
    <style>
        html, body { margin: 0; height: 100%; }
    </style>
</head>
<body>
@php
    $sources = [];
    foreach ($urlsToDocs as $title => $url) {
        $sources[] = [
            'title' => (string) $title,
            'slug' => \Illuminate\Support\Str::slug((string) $title) ?: 'docs',
            'url' => $url,
        ];
    }
    $scalarConfig = [
        'sources' => $sources,
        'persistAuth' => (bool) config('l5-swagger.defaults.ui.authorization.persist_authorization', true),
    ];
@endphp
<script id="api-reference" type="application/json">{!! json_encode($scalarConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!}</script>
<script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
</body>
</html>
