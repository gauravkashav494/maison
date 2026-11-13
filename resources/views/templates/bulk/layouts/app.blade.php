<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @php
        // The store's own colour from Bulk Order · Settings; only a plain hex value is let through.
        $brand = (string) tsetting('site.brand_color', '#0f4c45');
        $brand = preg_match('/^#[0-9a-fA-F]{6}$/', $brand) ? $brand : '#0f4c45';
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $brand }}">

    <x-seo :seo="$seo ?? null" />
    <x-app-head />

    @vite(['resources/templates/bulk/css/app.css', 'resources/templates/bulk/js/app.js'])
    <style>:root { --bk-brand: {{ $brand }}; }</style>
    @stack('head')
</head>
<body class="flex min-h-full flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded focus:bg-white focus:px-4 focus:py-2">Skip to content</a>

    @include('partials.header')

    <main id="main" class="flex-1">
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.cart-drawer')
    @include('partials.cookie-banner')

    @stack('scripts')
</body>
</html>
