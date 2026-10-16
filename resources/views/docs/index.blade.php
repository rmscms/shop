<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentationTitle }}</title>
    <style>
        html, body { margin: 0; height: 100%; }
        #app { height: 100vh; }
    </style>
</head>
<body>
@php
    $docUrl = $urlToDocs ?? null;
    if (!$docUrl && !empty($urlsToDocs) && is_array($urlsToDocs)) {
        $docUrl = reset($urlsToDocs);
    }
    $docUrl = preg_replace('/\?.*$/', '', (string) $docUrl) ?: url('/docs');
@endphp
<div id="app"></div>
<script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
<script>
  Scalar.createApiReference('#app', {
    url: @json($docUrl),
    persistAuth: @json((bool) config('l5-swagger.defaults.ui.authorization.persist_authorization', true)),
    layout: 'modern',
    theme: 'default'
  });
</script>
</body>
</html>
