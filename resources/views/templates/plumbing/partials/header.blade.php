@php
    $p = tsetting('site');
    $usps = array_values(array_filter((array) ($p['usp_strip'] ?? [])));
    $links = $menus['header'] ?? collect();
    $cats = $navCategories ?? collect();
    $isHome = request()->routeIs('home');
    $phone = $p['support_phone'] ?? ($site['contact_phone'] ?? null);
@endphp

{{-- Top strip: trust promises + support (desktop only) --}}
@if($usps || $phone)
    <div class="hidden bg-deep text-white lg:block">
        <div class="p-container flex h-9 items-center justify-between usp-strip">
            <ul class="flex items-center gap-6">
                @foreach($usps as $u)<li class="flex items-center gap-1.5 text-white/85"><x-ico name="check" :size="13" :stroke="2.5" class="text-accent" /> {{ $u }}</li>@endforeach
            </ul>
            <div class="flex items-center gap-5 text-white/85">
                @if($phone)<a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="flex items-center gap-1.5 hover:text-white"><x-ico name="phone" :size="13" /> {{ $phone }}</a>@endif
                <a href="{{ route('track') }}" class="flex items-center gap-1.5 hover:text-white"><x-ico name="truck" :size="14" /> Track order</a>
                <a href="{{ $p['bulk_cta_url'] ?? '/contact' }}" class="flex items-center gap-1.5 rounded-md bg-accent px-2.5 py-1 text-ink hover:bg-accent-dark hover:text-white"><x-ico name="building" :size="13" /> Bulk quote</a>
            </div>
        </div>
    </div>
@endif

<header x-data="header()" class="app-bar sticky top-0 z-50 bg-white transition-shadow" :class="scrolled && 'shadow-[0_6px_24px_-14px_rgba(6,59,115,.35)]'">
    {{-- Main row: logo · search · actions --}}
    <div class="p-container flex h-14 items-center gap-3 lg:h-[76px] lg:gap-6">
        @unless($isHome)<button type="button" @click="$store.app.back()" class="icon-btn -ml-2 shrink-0 lg:hidden" aria-label="Back"><x-ico name="arrow-left" :size="22" /></button>@endunless
        <button type="button" @click="$store.ui.openMenu()" class="icon-btn shrink-0 lg:hidden {{ $isHome ? '-ml-2' : '' }}" aria-label="Open menu"><x-ico name="menu" :size="22" /></button>

        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2" aria-label="{{ $site['name'] ?? config('app.name') }} — home">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-deep text-white lg:h-11 lg:w-11"><x-ico name="droplet" :size="22" :stroke="2" /></span>
            <span class="font-display text-xl font-800 leading-none tracking-tight lg:text-[1.45rem]"><span class="font-extrabold text-deep">{{ $p['logo_primary'] ?? 'Plumb' }}</span><span class="font-extrabold text-bright">{{ $p['logo_accent'] ?? 'Kart' }}</span></span>
        </a>

        {{-- Prominent search (desktop) --}}
        <div class="hidden min-w-0 flex-1 lg:block lg:px-4">
            @include('partials.search-box')
        </div>

        <div class="ml-auto flex items-center gap-0.5 lg:gap-1">
            <button type="button" @click="$store.ui.openSearch()" class="icon-btn lg:hidden" aria-label="Search"><x-ico name="search" :size="22" /></button>
            @if($phone)
                <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="hidden items-center gap-2.5 rounded-xl px-3 py-1.5 hover:bg-sky xl:flex">
                    <span class="grid h-9 w-9 place-items-center rounded-full bg-sky text-primary"><x-ico name="headset" :size="18" /></span>
                    <span class="leading-tight"><span class="block text-[0.6875rem] font-semibold uppercase tracking-wider text-slate">Expert support</span><span class="block text-sm font-bold text-ink">{{ $phone }}</span></span>
                </a>
            @endif
            <a href="{{ auth()->check() ? route('account.index') : route('login') }}" class="icon-btn hidden lg:grid" aria-label="Account" title="{{ auth()->check() ? 'My account' : 'Login' }}"><x-ico name="user" :size="22" /></a>
            <a href="{{ route('account.wishlist') }}" class="icon-btn relative hidden lg:grid" aria-label="Wishlist"><x-ico name="heart" :size="22" /><span x-show="$store.wishlist.count" x-cloak class="absolute right-1 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-accent px-1 text-[0.625rem] font-bold text-ink" x-text="$store.wishlist.count"></span></a>
            <button type="button" @click="$store.ui.openCart()" class="relative flex h-10 items-center gap-2 rounded-xl px-2 text-ink hover:bg-sky lg:bg-primary lg:px-4 lg:text-white lg:hover:bg-deep" aria-label="Open cart">
                <x-ico name="cart" :size="22" />
                <span class="hidden text-sm font-bold lg:block">Cart</span>
                <span x-show="$store.cart.count" x-cloak class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-accent px-1 text-[0.6875rem] font-extrabold text-ink lg:static lg:ml-0.5 lg:h-5 lg:rounded-md lg:bg-white/20 lg:text-white" x-text="$store.cart.count"></span>
            </button>
        </div>
    </div>

    {{-- Category navigation bar (desktop) --}}
    <nav class="hidden bg-primary text-white lg:block" aria-label="Categories" @mouseleave="scheduleClose()">
        <div class="p-container relative flex items-center gap-1">
            <button type="button" @mouseenter="openMega('all')" @click="mega = mega === 'all' ? null : 'all'" class="nav-link mr-1 bg-deep pr-4 hover:bg-deep" :class="mega === 'all' && 'is-open'">
                <x-ico name="grid" :size="18" /> All categories <x-ico name="chevron-down" :size="14" class="opacity-70" />
            </button>
            <div class="flex h-11 flex-1 flex-wrap items-center gap-0.5 overflow-hidden">
                @foreach($cats->take(9) as $c)
                    <a href="{{ $c->url }}" @mouseenter="{{ $c->children->isNotEmpty() ? "openMega('c{$c->id}')" : 'scheduleClose()' }}" class="nav-link" :class="mega === 'c{{ $c->id }}' && 'is-open'">{{ $c->name }}@if($c->children->isNotEmpty())<x-ico name="chevron-down" :size="12" class="opacity-70" />@endif</a>
                @endforeach
            </div>
            @if($links->isNotEmpty())
                <div class="flex items-center gap-0.5 border-l border-white/20 pl-2" @mouseenter="scheduleClose()">
                    @foreach($links->take(4) as $item)
                        <a href="{{ $item->href }}" @if($item->opens_in_new_tab) target="_blank" rel="noopener" @endif class="nav-link {{ $item->is_accent ? 'text-accent' : '' }}">@if($item->is_accent)<x-ico name="percent" :size="14" />@endif{{ $item->label }}</a>
                    @endforeach
                </div>
            @endif

            {{-- Mega panels --}}
            <div x-show="mega !== null" x-cloak x-transition.opacity.duration.150ms @mouseenter="openMega(mega)" class="mega text-ink">
                <div x-show="mega === 'all'" class="p-container grid grid-cols-3 gap-x-10 gap-y-6 py-7 xl:grid-cols-4">
                    @foreach($cats as $c)
                        <div class="flex gap-3">
                            <a href="{{ $c->url }}" class="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-sky">@if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover" loading="lazy">@endif</a>
                            <div class="min-w-0">
                                <a href="{{ $c->url }}" class="block text-sm font-bold hover:text-primary">{{ $c->name }}</a>
                                @if($c->children->isNotEmpty())
                                    <p class="mt-0.5 truncate text-xs text-slate">@foreach($c->children->take(3) as $sub){{ $sub->name }}@if(!$loop->last) · @endif @endforeach</p>
                                @else
                                    <p class="mt-0.5 truncate text-xs text-slate">{{ $c->tagline }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @foreach($cats->take(9) as $c)
                    @if($c->children->isNotEmpty())
                        <div x-show="mega === 'c{{ $c->id }}'" class="p-container grid grid-cols-[1fr_18rem] gap-10 py-7">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate">{{ $c->name }}</p>
                                <div class="mt-3 grid grid-cols-3 gap-x-6 gap-y-1">
                                    @foreach($c->children as $sub)<a href="{{ $sub->url }}" class="mega-link">{{ $sub->name }}</a>@endforeach
                                    <a href="{{ $c->url }}" class="mega-link font-bold text-primary">All {{ Str::lower($c->name) }} →</a>
                                </div>
                            </div>
                            <a href="{{ $c->url }}" class="relative block aspect-[4/3] overflow-hidden rounded-xl bg-sky">
                                @if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover" loading="lazy">@endif
                                <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-deep/85 to-transparent p-3 text-sm font-bold text-white">{{ $c->tagline ?: $c->name }}</span>
                            </a>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </nav>

    {{-- Mobile: search bar + category chips --}}
    <div class="p-container pb-2.5 lg:hidden">
        <button type="button" @click="$store.ui.openSearch()" class="field flex h-11 items-center gap-2 rounded-xl bg-canvas text-left text-mist">
            <x-ico name="search" :size="18" class="text-slate" />
            <span class="truncate">{{ $p['search_placeholder'] ?? 'Search products…' }}</span>
        </button>
    </div>
    <div class="no-scrollbar flex gap-2 overflow-x-auto border-t border-line-soft px-4 py-2 lg:hidden">
        <button type="button" @click="$store.ui.openMenu()" class="chip chip-active"><x-ico name="grid" :size="14" /> All</button>
        @foreach($cats->take(10) as $c)<a href="{{ $c->url }}" class="chip">{{ $c->name }}</a>@endforeach
    </div>
</header>
