@php
    $g = tsetting('site');
    $offer = $g['offer_strip_text'] ?? null;
    $quickLinks = $menus['header'] ?? collect();
    $cats = $navCategories ?? collect();
@endphp

{{-- Offer strip --}}
@if($offer)
    <div class="hidden bg-saffron-light text-saffron-dark lg:block">
        <div class="g-container flex items-center justify-center gap-2 py-1.5 text-center text-xs font-semibold sm:text-[0.8125rem]">
            <x-ico name="tag" :size="14" class="hidden sm:block" />
            <span>{{ $offer }}</span>
            @if(!empty($g['offer_strip_link_label']))
                <a href="{{ $g['offer_strip_link_url'] ?? '#' }}" class="underline underline-offset-2 hover:text-ink">{{ $g['offer_strip_link_label'] }}</a>
            @endif
        </div>
    </div>
@endif

<header x-data="header()" class="app-bar sticky top-0 z-50 bg-white transition-shadow" :class="scrolled && 'shadow-[0_2px_14px_-6px_rgba(27,31,28,.25)]'">
    {{-- Main row --}}
    <div class="g-container flex h-14 items-center gap-3 lg:h-16 lg:gap-6">
        {{-- Inner pages get a back arrow, like a native navigation bar --}}
        @unless(request()->routeIs('home'))<button type="button" @click="$store.app.back()" class="-ml-2 grid h-10 w-9 shrink-0 place-items-center rounded-lg text-ink hover:bg-paper lg:hidden" aria-label="Back"><x-ico name="arrow-left" :size="22" /></button>@endunless
        {{-- Logo --}}
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2" aria-label="{{ $site['name'] ?? config('app.name') }} — home">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-leaf text-white"><x-ico name="leaf" :size="20" :stroke="2.2" /></span>
            <span class="text-[1.2rem] font-extrabold leading-none tracking-tight">
                <span class="text-leaf-dark">{{ $g['logo_primary'] ?? 'Maison' }}</span><span class="text-saffron">{{ $g['logo_accent'] ?? 'Fresh' }}</span>
            </span>
        </a>

        {{-- Location --}}
        <button type="button" @click="$store.ui.openLocation()" class="group flex min-w-0 items-center gap-2 rounded-lg px-1 py-1 text-left hover:bg-paper lg:px-2">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-leaf-light text-leaf"><x-ico name="pin" :size="16" /></span>
            <span class="min-w-0 {{ request()->routeIs('home') ? '' : 'max-lg:hidden' }}">
                <span class="block truncate text-[0.8125rem] font-extrabold leading-tight">{{ $g['delivery_promise'] ?? 'Delivery in minutes' }}</span>
                <span class="flex items-center gap-1 text-xs text-slate"><span class="truncate" x-text="$store.location.area || @js($g['delivery_area'] ?? 'Choose location')">{{ $g['delivery_area'] ?? 'Choose location' }}</span><x-ico name="chevron-down" :size="12" /></span>
            </span>
        </button>

        {{-- Desktop search --}}
        <div class="hidden flex-1 lg:block">
            @include('partials.search-box')
        </div>

        {{-- Actions --}}
        <div class="ml-auto flex items-center gap-1 sm:gap-2">
            <a href="{{ route('account.wishlist') }}" class="relative hidden h-10 w-10 place-items-center rounded-lg text-ink hover:bg-paper sm:grid" aria-label="Saved items">
                <x-ico name="heart" :size="20" />
                <span x-show="$store.wishlist.count" x-cloak class="absolute right-1 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-berry px-1 text-[0.625rem] font-bold text-white" x-text="$store.wishlist.count"></span>
            </a>
            <a href="{{ auth()->check() ? route('account.index') : route('login') }}" class="hidden h-10 items-center gap-2 rounded-lg px-2 text-sm font-semibold hover:bg-paper sm:flex">
                <x-ico name="user" :size="20" />
                <span class="hidden xl:block">{{ auth()->check() ? Str::of(auth()->user()->name)->before(' ') : 'Login' }}</span>
            </a>
            <button type="button" @click="$store.ui.openCart()" class="btn btn-primary relative h-10 gap-2 px-3 sm:px-4" aria-label="Open cart">
                <x-ico name="cart" :size="20" />
                <span class="hidden flex-col items-start leading-none sm:flex">
                    <span class="text-[0.6875rem] font-semibold opacity-90" x-text="$store.cart.count ? $store.cart.count + ' item' + ($store.cart.count > 1 ? 's' : '') : 'My cart'">My cart</span>
                    <span class="mt-0.5 text-[0.8125rem] font-extrabold" x-show="$store.cart.count" x-cloak x-text="$store.cart.total_formatted"></span>
                </span>
                <span x-show="$store.cart.count" x-cloak class="absolute -right-1.5 -top-1.5 grid h-5 min-w-5 place-items-center rounded-full bg-saffron px-1 text-[0.6875rem] font-extrabold text-white sm:hidden" x-text="$store.cart.count"></span>
            </button>
        </div>
    </div>

    {{-- Mobile search --}}
    <div class="g-container pb-2.5 lg:hidden">
        <button type="button" @click="$store.ui.openSearch()" class="field flex items-center gap-2 text-left text-mist">
            <x-ico name="search" :size="18" class="text-slate" />
            <span class="truncate">{{ $g['search_placeholder'] ?? 'Search products…' }}</span>
        </button>
    </div>

    {{-- Category bar (desktop) --}}
    <nav class="hidden border-t border-line lg:block" aria-label="Categories" @mouseleave="scheduleClose()">
        <div class="g-container flex items-center gap-1">
            <button type="button" @mouseenter="openMega('all')" @click="mega = mega === 'all' ? null : 'all'" class="flex h-11 items-center gap-2 border-r border-line pr-4 text-sm font-bold" :class="mega === 'all' && 'text-leaf'">
                <x-ico name="grid" :size="18" /> All categories <x-ico name="chevron-down" :size="14" />
            </button>
            <div class="flex flex-1 items-center gap-0.5 overflow-hidden">
                @foreach($cats->take(8) as $c)
                    <a href="{{ $c->url }}" @mouseenter="openMega('c{{ $c->id }}')" class="{{ $loop->index >= 5 ? 'hidden xl:flex' : 'flex' }} h-11 shrink-0 items-center gap-1 px-3 text-[0.8125rem] font-semibold text-ink transition-colors hover:text-leaf" :class="mega === 'c{{ $c->id }}' && 'text-leaf'">
                        {{ $c->name }}@if($c->children->isNotEmpty())<x-ico name="chevron-down" :size="12" class="text-mist" />@endif
                    </a>
                @endforeach
            </div>
            @if($quickLinks->isNotEmpty())
                <div class="flex items-center gap-1 border-l border-line pl-3" @mouseenter="scheduleClose()">
                    @foreach($quickLinks->take(4) as $item)
                        <a href="{{ $item->href }}" @if($item->opens_in_new_tab) target="_blank" rel="noopener" @endif class="flex h-11 items-center gap-1 px-2.5 text-[0.8125rem] font-bold {{ $item->is_accent ? 'text-berry' : 'text-slate hover:text-leaf' }}">
                            @if($item->is_accent)<x-ico name="tag" :size="14" />@endif{{ $item->label }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Mega panels --}}
        <div x-show="mega !== null" x-cloak x-transition.opacity.duration.150ms @mouseenter="openMega(mega)" class="absolute inset-x-0 top-full border-t border-line bg-white shadow-[0_24px_40px_-24px_rgba(27,31,28,.35)]">
            <div x-show="mega === 'all'" class="g-container grid grid-cols-4 gap-x-8 gap-y-3 py-6 xl:grid-cols-6">
                @foreach($cats as $c)
                    <a href="{{ $c->url }}" class="group flex items-center gap-3 rounded-xl p-2 hover:bg-paper">
                        <span class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-leaf-light">@if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover" loading="lazy">@endif</span>
                        <span class="min-w-0"><span class="block truncate text-sm font-bold group-hover:text-leaf">{{ $c->name }}</span><span class="block truncate text-xs text-slate">{{ $c->tagline }}</span></span>
                    </a>
                @endforeach
            </div>
            @foreach($cats->take(8) as $c)
                @if($c->children->isNotEmpty())
                    <div x-show="mega === 'c{{ $c->id }}'" class="g-container grid grid-cols-[1fr_16rem] gap-8 py-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-mist">{{ $c->name }}</p>
                            <div class="mt-3 grid grid-cols-3 gap-x-6 gap-y-2">
                                @foreach($c->children as $sub)
                                    <a href="{{ $sub->url }}" class="rounded-lg px-2 py-1.5 text-sm font-semibold hover:bg-paper hover:text-leaf">{{ $sub->name }}</a>
                                @endforeach
                                <a href="{{ $c->url }}" class="rounded-lg px-2 py-1.5 text-sm font-bold text-leaf">View all {{ Str::lower($c->name) }} →</a>
                            </div>
                        </div>
                        <a href="{{ $c->url }}" class="relative block aspect-[4/3] overflow-hidden rounded-xl bg-leaf-light">
                            @if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover" loading="lazy">@endif
                            <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink/70 to-transparent p-3 text-sm font-bold text-white">{{ $c->tagline ?: $c->name }}</span>
                        </a>
                    </div>
                @endif
            @endforeach
        </div>
    </nav>

    {{-- Category chips (mobile) --}}
    <div class="no-scrollbar flex gap-2 overflow-x-auto border-t border-line px-4 py-2 lg:hidden">
        <button type="button" @click="$store.ui.openCategories()" class="chip chip-active"><x-ico name="grid" :size="14" /> All</button>
        @foreach($cats->take(10) as $c)
            <a href="{{ $c->url }}" class="chip">{{ $c->name }}</a>
        @endforeach
        @foreach($quickLinks->where('is_accent', true)->take(1) as $item)
            <a href="{{ $item->href }}" class="chip border-berry text-berry">{{ $item->label }}</a>
        @endforeach
    </div>
</header>
