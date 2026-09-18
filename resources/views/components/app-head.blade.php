{{-- Installable web app tags shared by every template's layout (manifest, iOS home-screen icon, full-screen mode). --}}
@php
    $tpl = template();
    $pwa = $tpl->pwa();
    $iconBase = '/templates/'.$tpl->id();
    $appName = $pwa['name'] ?? setting('site.name', config('app.name'));
@endphp
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $appName }}">
<meta name="application-name" content="{{ $appName }}">
<link rel="apple-touch-icon" href="{{ $iconBase }}/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="192x192" href="{{ $iconBase }}/icon-192.png">
