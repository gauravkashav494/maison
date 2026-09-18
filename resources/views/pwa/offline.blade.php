<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $pwa['theme_color'] }}">
    <title>You’re offline — {{ setting('site.name', config('app.name')) }}</title>
    {{-- Self-contained: this page is cached by the service worker and must render without the template bundle. --}}
    <style>
        html, body { height: 100%; margin: 0; }
        body { display: grid; place-items: center; padding: max(env(safe-area-inset-top), 24px) 24px 24px; box-sizing: border-box; background: {{ $pwa['background_color'] }}; color: #1b1f1c; font: 16px/1.5 system-ui, -apple-system, 'Segoe UI', sans-serif; text-align: center; }
        img { width: 88px; height: 88px; border-radius: 22px; }
        h1 { margin: 24px 0 8px; font-size: 1.5rem; }
        p { margin: 0 auto; max-width: 22rem; color: #5b625d; }
        button { margin-top: 24px; height: 48px; padding: 0 28px; border: 0; border-radius: 999px; background: #1b1f1c; color: #fff; font: inherit; font-weight: 600; }
    </style>
</head>
<body>
    <div>
        <img src="/templates/{{ $templateId }}/icon-192.png" alt="">
        <h1>You’re offline</h1>
        <p>{{ setting('site.name', config('app.name')) }} needs a connection to show fresh prices and stock. Your cart is safe — reconnect and try again.</p>
        <button type="button" onclick="location.reload()">Try again</button>
    </div>
</body>
</html>
