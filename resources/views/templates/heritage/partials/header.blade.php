@php
    $h = tsetting('site');
    $promos = array_values(array_filter((array) ($h['promo_messages'] ?? [])));
    $links = $menus['header'] ?? collect();
    $cats = $navCategories ?? collect();
@endphp

{{-- Promo bar --}}
@if($promos)
    <div class="bg-red text-cream" x-data="promoBar({{ count($promos) }})">
        <div class="h-container flex h-9 items-center justify-center gap-4 text-[0.75rem] font-medium tracking-wide sm:text-[0.8125rem]">
            <span class="hidden h-px w-8 bg-gold-light/70 sm:block"></span>
            <div class="relative h-9 min-w-0 flex-1 overflow-hidden text-center sm:flex-none sm:min-w-[24rem]">
                @foreach($promos as $i => $m)
                    <span x-show="index === {{ $i }}" x-transition:enter="transition duration-500" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="absolute inset-0 flex items-center justify-center truncate px-2" @if($i > 0) x-cloak @endif>{{ $m }}</span>
                @endforeach
            </div>
            @if(!empty($h['announcement_cta_label']))<a href="{{ $h['announcement_cta_url'] ?? '#' }}" class="hidden shrink-0 font-semibold text-gold-light underline-offset-4 hover:underline sm:block">{{ $h['announcement_cta_label'] }} →</a>@endif
            <span class="hidden h-px w-8 bg-gold-light/70 sm:block"></span>
        </div>
    </div>
@endif

<header x-data="header()" class="sticky top-0 z-50 border-b border-line bg-warm/95 backdrop-blur transition-shadow" :class="scrolled && 'shadow-[0_6px_24px_-16px_rgba(41,35,31,.45)]'">
    {{-- Main row --}}
    <div class="h-container flex h-[4.25rem] items-center gap-2 sm:gap-3 lg:h-20 lg:gap-8">
        <button type="button" @click="$store.ui.openMenu()" class="grid h-10 w-9 shrink-0 place-items-center rounded-lg text-ink hover:bg-cream lg:hidden" aria-label="Open menu"><x-ico name="menu" :size="22" /></button>

        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5" aria-label="{{ $site['name'] ?? config('app.name') }} — home">
            <span class="grid h-10 w-10 place-items-center rounded-full border border-gold bg-cream text-red"><x-ico name="diamond" :size="16" :stroke="1.8" /></span>
            <span class="font-serif text-[1.1rem] leading-none tracking-tight sm:text-[1.35rem] lg:text-[1.6rem]">
                <span class="font-semibold text-red">{{ $h['logo_primary'] ?? 'Annapurna' }}</span> <span class="font-medium italic text-gold">{{ $h['logo_accent'] ?? 'Organics' }}</span>
            </span>
        </a>

        {{-- Desktop search --}}
        <div class="hidden flex-1 lg:block lg:max-w-2xl">
            @include('partials.search-box')
        </div>

        <div class="hidden items-center gap-2.5 xl:flex">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-cream text-gold"><x-ico name="truck" :size="18" /></span>
            <span class="text-xs leading-tight text-muted"><span class="block font-semibold text-ink">{{ $h['delivery_note'] ?? 'Pan-India delivery' }}</span>Free over {{ money((int) setting('site.free_shipping_threshold', 999)) }}</span>
        </div>

        {{-- Actions --}}
        <div class="ml-auto flex items-center gap-0.5 sm:gap-1">
            <button type="button" @click="$store.ui.openSearch()" class="grid h-10 w-10 place-items-center rounded-lg hover:bg-cream lg:hidden" aria-label="Search"><x-ico name="search" :size="21" /></button>
            <a href="{{ auth()->check() ? route('account.index') : route('login') }}" class="hidden h-10 items-center gap-2 rounded-lg px-2.5 text-sm font-medium hover:bg-cream sm:flex" aria-label="Account">
                <x-ico name="user" :size="21" /><span class="hidden lg:block">{{ auth()->check() ? Str::of(auth()->user()->name)->before(' ') : 'Login' }}</span>
            </a>
            <a href="{{ route('account.wishlist') }}" class="relative hidden h-10 w-10 place-items-center rounded-lg hover:bg-cream sm:grid" aria-label="Wishlist">
                <x-ico name="heart" :size="21" />
                <span x-show="$store.wishlist.count" x-cloak class="absolute right-0.5 top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-gold px-1 text-[0.625rem] font-bold text-maroon-deep" x-text="$store.wishlist.count"></span>
            </a>
            <button type="button" @click="$store.ui.openCart()" class="relative flex h-10 items-center gap-2 rounded-lg px-2.5 hover:bg-cream" aria-label="Open cart">
                <x-ico name="bag" :size="21" />
                <span class="hidden text-sm font-semibold tabular lg:block" x-text="$store.cart.count ? $store.cart.total_formatted : 'Cart'">Cart</span>
                <span x-show="$store.cart.count" x-cloak class="absolute right-0.5 top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-red px-1 text-[0.625rem] font-bold text-white lg:static lg:h-5 lg:min-w-5 lg:text-[0.6875rem]" x-text="$store.cart.count"></span>
            </button>
        </div>
    </div>

    {{-- Main navigation (desktop) --}}
    <nav class="hidden border-t border-line-soft lg:block" aria-label="Main navigation" @mouseleave="scheduleClose()">
        <div class="h-container flex items-center gap-7">
            <a href="{{ route('shop.index') }}" @mouseenter="openMega('shop')" class="nav-link flex items-center gap-1.5 text-red" :class="mega === 'shop' && 'is-open'"><x-ico name="grid" :size="16" /> Shop all <x-ico name="chevron-down" :size="14" class="text-gold" /></a>
            @foreach($cats->take(6) as $c)
                <a href="{{ $c->url }}" @mouseenter="openMega({{ $c->children->isNotEmpty() ? "'c{$c->id}'" : 'null' }})" class="nav-link flex items-center gap-1 {{ $loop->index >= 4 ? 'hidden 2xl:flex' : '' }}" :class="mega === 'c{{ $c->id }}' && 'is-open'">{{ $c->name }}@if($c->children->isNotEmpty())<x-ico name="chevron-down" :size="13" class="text-gold" />@endif</a>
            @endforeach
            <div class="ml-auto flex items-center gap-6" @mouseenter="scheduleClose()">
                @foreach($links->take(4) as $item)
                    <a href="{{ $item->href }}" @if($item->opens_in_new_tab) target="_blank" rel="noopener" @endif class="nav-link {{ $loop->index >= 3 ? 'hidden 2xl:block' : '' }} {{ $item->is_accent ? 'text-red' : 'text-muted hover:text-ink' }}">{{ $item->is_accent ? '✦ ' : '' }}{{ $item->label }}</a>
                @endforeach
            </div>
        </div>

        {{-- Mega panels --}}
        <div x-show="mega !== null" x-cloak x-transition.opacity.duration.150ms @mouseenter="openMega(mega)" class="absolute inset-x-0 top-full border-t border-gold/40 bg-warm shadow-[0_30px_50px_-30px_rgba(41,35,31,.45)]">
            {{-- All categories --}}
            <div x-show="mega === 'shop'" class="h-container grid grid-cols-[1fr_20rem] gap-10 py-8">
                <div class="grid grid-cols-3 gap-x-8 gap-y-6 xl:grid-cols-4">
                    @foreach($cats as $c)
                        <div>
                            <a href="{{ $c->url }}" class="font-serif text-[1.05rem] font-semibold text-maroon hover:text-red">{{ $c->name }}</a>
                            @if($c->children->isNotEmpty())
                                <ul class="mt-2 space-y-1.5">
                                    @foreach($c->children->take(5) as $sub)<li><a href="{{ $sub->url }}" class="text-[0.8125rem] text-muted hover:text-red">{{ $sub->name }}</a></li>@endforeach
                                </ul>
                            @else
                                <p class="mt-1 text-[0.8125rem] text-muted">{{ $c->tagline }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
                @php $feature = $cats->firstWhere('slug', 'gift-boxes') ?? $cats->first(); @endphp
                @if($feature)
                    <a href="{{ $feature->url }}" class="gold-frame relative block overflow-hidden rounded-xl bg-maroon text-cream">
                        @if($feature->image_url)<img src="{{ $feature->image_url }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-70" loading="lazy">@endif
                        <span class="relative flex h-full flex-col justify-end p-6"><span class="eyebrow text-gold-light">Featured</span><span class="mt-2 font-serif text-2xl font-semibold">{{ $feature->name }}</span><span class="mt-1 text-sm text-cream/85">{{ $feature->tagline }}</span></span>
                    </a>
                @endif
            </div>
            {{-- Per-category --}}
            @foreach($cats->take(6) as $c)
                @if($c->children->isNotEmpty())
                    <div x-show="mega === 'c{{ $c->id }}'" class="h-container grid grid-cols-[1fr_18rem] gap-10 py-8">
                        <div>
                            <p class="eyebrow">{{ $c->name }}</p>
                            <div class="mt-4 grid grid-cols-3 gap-x-8 gap-y-2">
                                @foreach($c->children as $sub)<a href="{{ $sub->url }}" class="rounded-md px-2 py-1.5 text-sm text-ink hover:bg-cream hover:text-red">{{ $sub->name }}</a>@endforeach
                                <a href="{{ $c->url }}" class="rounded-md px-2 py-1.5 text-sm font-semibold text-red">View all {{ Str::lower($c->name) }} →</a>
                            </div>
                        </div>
                        <a href="{{ $c->url }}" class="gold-frame relative block aspect-[4/3] overflow-hidden rounded-xl bg-cream-dark">
                            @if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover" loading="lazy">@endif
                            <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-maroon-deep/85 to-transparent p-4 text-sm font-medium text-cream">{{ $c->tagline ?: $c->name }}</span>
                        </a>
                    </div>
                @endif
            @endforeach
        </div>
    </nav>

    {{-- Category chips (mobile) --}}
    <div class="no-scrollbar flex gap-2 overflow-x-auto border-t border-line-soft px-4 py-2 lg:hidden">
        <button type="button" @click="$store.ui.openMenu()" class="chip chip-active"><x-ico name="grid" :size="14" /> All</button>
        @foreach($cats->take(10) as $c)<a href="{{ $c->url }}" class="chip">{{ $c->name }}</a>@endforeach
    </div>
</header>
