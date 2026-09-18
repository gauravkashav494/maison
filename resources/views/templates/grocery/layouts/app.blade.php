{{-- Indian Grocery template layout. Shares <x-seo> and the JSON APIs with every template. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1e8a4c">

    <x-seo :seo="$seo ?? null" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(template()->assets())
    @stack('head')
</head>
<body class="flex min-h-full flex-col pb-16 lg:pb-0">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-leaf focus:px-4 focus:py-2 focus:text-white">Skip to content</a>

    @include('partials.header')

    <main id="main" class="flex-1">
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.mobile-tab-bar')
    @include('partials.mobile-menu')
    @include('partials.categories-sheet')
    @include('partials.location-sheet')
    @include('partials.search-overlay')
    @include('partials.cart-drawer')
    @include('partials.pack-picker')
    @include('partials.toast')
    @include('partials.cookie-banner')

    @stack('scripts')
</body>
</html>
