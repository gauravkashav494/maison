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
<header class="app-bar sticky top-0 z-40 border-b border-line-soft bg-white/95 backdrop-blur lg:hidden" x-data>
    <div class="flex h-[3.75rem] items-center gap-1 px-2">
        <button type="button" @click="$store.ui.openMenu()" class="icon-btn" aria-label="Open menu"><x-ico name="menu" :size="24" /></button>
        <a href="{{ route('home') }}" class="flex min-w-0 flex-1 items-center gap-2" aria-label="{{ $biz['name'] }} home">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-primary text-white"><x-ico name="droplet" :size="20" :stroke="2" /></span>
            <span class="truncate font-display text-lg font-extrabold text-deep">{{ $p['logo_primary'] ?? 'Pipe' }}<span class="text-bright">{{ $p['logo_accent'] ?? 'Care' }}</span></span>
        </a>
        @if($biz['whatsapp'])<a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="grid h-10 w-10 place-items-center rounded-full bg-success-light text-whatsapp-dark" aria-label="WhatsApp"><x-ico name="whatsapp" :size="20" /></a>@endif
        <a href="{{ $biz['phone_href'] }}" class="ml-1 grid h-10 w-10 place-items-center rounded-full bg-primary text-white shadow-glow" aria-label="Call {{ $biz['phone'] }}"><x-ico name="phone" :size="19" /></a>
    </div>
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
        <button type="button" @click="$store.ui.openMenu()" class="icon-btn" aria-label="Open menu"><x-ico name="menu" :size="22" /></button>
        <a href="{{ $biz['phone_href'] }}" class="icon-btn bg-sky text-primary" aria-label="Call {{ $biz['phone'] }}"><x-ico name="phone" :size="20" /></a>
    </div>
</header>
@endif