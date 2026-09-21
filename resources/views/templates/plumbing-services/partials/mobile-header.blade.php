{{-- Mobile / tablet app header (< lg).
     Home: gradient header with the service location, account, a large search bar and the horizontal service rail.
     Other pages: compact app bar with back arrow, title and a contact shortcut ($appBar = ['title' => ..., 'back' => bool]). --}}
@php
    $p = tsetting('site');
    $isHome = request()->routeIs('home');
    $bar = $appBar ?? ['title' => isset($seo) ? $seo->title : '', 'back' => true];
    $servicesNav = $navServices ?? collect();
    $suggestions = array_values($p['search_suggestions'] ?? []);
@endphp
@if($isHome)
<header class="app-bar app-header sticky top-0 z-40 lg:hidden">
    <div class="px-4 pt-3">
        <div class="flex items-center justify-between gap-3">
            <button type="button" @click="$store.ui.openLocation()" class="min-w-0 text-left" x-data aria-label="Change service area">
                <span class="flex items-center gap-1 text-[0.7rem] font-semibold uppercase tracking-wider text-white/75"><x-ico name="map-pin" :size="12" /> Plumbing services in</span>
                <span class="mt-0.5 flex items-center gap-1 font-display text-xl font-extrabold leading-none"><span x-text="$store.area.name || @js($biz['default_area'] ?: 'your area')">{{ $biz['default_area'] ?: 'your area' }}</span> <x-ico name="chevron-down" :size="18" class="text-white/80" /></span>
                <span class="mt-1 block text-xs text-white/75" x-text="$store.area.current?.response ? `Emergency response ${$store.area.current.response}` : @js($biz['response'])">{{ $biz['response'] }}</span>
            </button>
            <div class="flex shrink-0 items-center gap-2">
                <a href="{{ $biz['phone_href'] }}" class="grid h-10 w-10 place-items-center rounded-full bg-white/15 text-white ring-1 ring-white/30" aria-label="Call {{ $biz['phone'] }}"><x-ico name="phone" :size="19" /></a>
                <a href="{{ auth()->check() ? route('account.index') : route('login') }}" class="grid h-10 w-10 place-items-center rounded-full bg-white text-primary" aria-label="Account"><x-ico name="user" :size="20" /></a>
            </div>
        </div>

        {{-- Search --}}
        <div class="mt-3" x-data="search(@js($suggestions), @js($p['search_placeholder'] ?? 'Search plumbing services'))">
            <form action="{{ route('services.index') }}" method="get" @submit.prevent="submit()" role="search" class="app-search">
                <x-ico name="search" :size="20" class="text-primary" />
                <input type="search" name="q" x-model="q" @focus="$store.ui.openSearch()" :placeholder="placeholder" enterkeyhint="search" autocomplete="off" class="min-w-0 flex-1 bg-transparent text-[0.95rem] font-medium placeholder:text-slate focus:outline-none" aria-label="Search plumbing services">
                <button type="button" @click="$store.ui.openContact()" class="grid h-8 w-8 place-items-center rounded-full bg-sky text-primary" aria-label="Contact options"><x-ico name="headset" :size="16" /></button>
            </form>
        </div>
    </div>

    {{-- Horizontal service categories --}}
    <nav class="no-scrollbar mt-3 flex gap-1 overflow-x-auto px-3 pb-3" aria-label="Service categories">
        @foreach($servicesNav->sortByDesc('is_emergency')->sortByDesc('is_popular')->take(10) as $s)
            <a href="{{ $s->url }}" class="flex w-[4.6rem] shrink-0 flex-col items-center gap-1.5 rounded-2xl px-1 py-1.5 text-center">
                <span class="grid h-11 w-11 place-items-center rounded-full {{ $s->is_emergency ? 'bg-danger text-white' : 'bg-white/14 text-white ring-1 ring-white/30' }}"><x-ico :name="$s->icon ?: 'wrench'" :size="22" :stroke="1.7" /></span>
                <span class="line-clamp-2 text-[0.66rem] font-semibold leading-tight text-white">{{ Str::contains($s->name, ' Plumbing') ? Str::before($s->name, ' Plumbing') : Str::before($s->name, ' Services') }}</span>
            </a>
        @endforeach
        <a href="{{ route('services.index') }}" class="flex w-[4.6rem] shrink-0 flex-col items-center gap-1.5 rounded-2xl px-1 py-1.5 text-center">
            <span class="grid h-11 w-11 place-items-center rounded-full bg-white text-primary"><x-ico name="grid" :size="20" /></span>
            <span class="text-[0.66rem] font-semibold leading-tight text-white">All services</span>
        </a>
    </nav>
</header>
@else
<header class="app-bar sticky top-0 z-40 border-b border-line-soft bg-white/95 backdrop-blur lg:hidden" x-data>
    <div class="flex h-14 items-center gap-1 px-2">
        @if($bar['back'] ?? true)
            <button type="button" @click="$store.app.back()" class="icon-btn" aria-label="Back"><x-ico name="arrow-left" :size="22" /></button>
        @else
            <a href="{{ route('home') }}" class="icon-btn" aria-label="Home"><x-ico name="home" :size="22" /></a>
        @endif
        <p class="min-w-0 flex-1 truncate font-display text-base font-extrabold" aria-hidden="true">{{ $bar["title"] ?? "" }}</p>
        <button type="button" @click="$store.ui.openSearch()" class="icon-btn" aria-label="Search"><x-ico name="search" :size="21" /></button>
        <a href="{{ $biz['phone_href'] }}" class="icon-btn bg-sky text-primary" aria-label="Call {{ $biz['phone'] }}"><x-ico name="phone" :size="20" /></a>
    </div>
</header>
@endif