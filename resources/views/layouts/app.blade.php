<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f6f3ee">

    <x-seo :seo="$seo ?? null" />
    <x-app-head />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=Manrope:wght@300;400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-full flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:bg-ink focus:px-4 focus:py-2 focus:text-ivory">Skip to content</a>

    @include('partials.header', ['transparent' => $transparentHeader ?? false])

    <main id="main" class="app-main-fixed flex-1">
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.mobile-tab-bar')
    @include('partials.app-install')
    @include('partials.mobile-menu')
    @include('partials.search-overlay')
    @include('partials.cart-drawer')
    @include('partials.quick-view')
    @include('partials.cookie-banner')

    @stack('scripts')
</body>
</html>
