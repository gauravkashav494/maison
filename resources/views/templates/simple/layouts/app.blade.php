<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">

    <x-seo :seo="$seo ?? null" />
    <x-app-head />

    @vite(['resources/templates/simple/css/app.css', 'resources/templates/simple/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-full flex-col bg-white">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:bg-ink focus:px-4 focus:py-2 focus:text-ivory">Skip to content</a>

    @include('partials.header')

    <main id="main" class="flex-1">
        @yield('content')
    </main>

    @include('partials.footer')

    {{-- The shared cart drawer, search overlay and consent banner: same behaviour everywhere. --}}
    @include('partials.cart-drawer')
    @include('partials.cookie-banner')

    @stack('scripts')
</body>
</html>
