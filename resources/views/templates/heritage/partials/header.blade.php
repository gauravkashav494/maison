@php
    $h = tsetting('site');
    $promos = array_values(array_filter((array) ($h['promo_messages'] ?? [])));
    $links = $menus['header'] ?? collect();
    $cats = $navCategories ?? collect();
    $needs = \Illuminate\Support\Facades\Cache::remember('heritage.nav_needs', 600, fn () => \App\Models\Collection::active()->orderBy('sort_order')->get(['id', 'name', 'slug', 'description']));
@endphp

{{-- Announcement ticker --}}
@if($promos)
    <div class="overflow-hidden bg-red text-cream" aria-label="Announcements">
        <div class="ticker py-2 text-[0.8125rem] font-medium">
            @for($r = 0; $r < 2; $r++)@foreach($promos as $m)<span>{{ $m }}</span>@endforeach @endfor
        </div>
    </div>
@endif

<header x-data="header()" class="sticky top-0 z-50 bg-warm transition-shadow" :class="scrolled && 'shadow-[0_6px_24px_-16px_rgba(41,35,31,.45)]'">
    {{-- Main row: round logo | centred search | icons --}}
    <div class="h-container flex h-[4.5rem] items-center gap-3 lg:h-24 lg:gap-8">
        <button type="button" @click="$store.ui.openMenu()" class="grid h-10 w-9 shrink-0 place-items-center rounded-lg text-ink hover:bg-cream lg:hidden" aria-label="Open menu"><x-ico name="menu" :size="22" /></button>

        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3" aria-label="{{ $site['name'] ?? config('app.name') }} — home">
            <span class="grid h-12 w-12 place-items-center rounded-full border-2 border-gold bg-cream text-red lg:h-[4.25rem] lg:w-[4.25rem]">
                <span class="flex flex-col items-center leading-none"><x-ico name="diamond" :size="14" :stroke="1.8" /><span class="mt-1 font-serif text-[0.5rem] font-semibold uppercase tracking-[0.1em] lg:text-[0.6rem]">{{ Str::substr($h['logo_primary'] ?? 'Annapurna', 0, 9) }}</span><span class="text-[0.42rem] uppercase tracking-[0.2em] text-gold lg:text-[0.5rem]">{{ $h['logo_sub'] ?? '' }}</span></span>
            </span>
            <span class="font-serif text-[1.15rem] leading-none sm:text-[1.35rem] lg:hidden"><span class="font-semibold text-red">{{ $h['logo_primary'] ?? 'Annapurna' }}</span> <span class="italic text-gold">{{ $h['logo_accent'] ?? '' }}</span></span>
        </a>

        <div class="hidden flex-1 lg:block lg:px-8 xl:px-16">
            @include('partials.search-box')
        </div>

        <div class="ml-auto flex items-center gap-0.5 sm:gap-2">
            <button type="button" @click="$store.ui.openSearch()" class="grid h-10 w-10 place-items-center rounded-lg hover:bg-cream lg:hidden" aria-label="Search"><x-ico name="search" :size="21" /></button>
            <a href="{{ auth()->check() ? route('account.index') : route('login') }}" class="hidden h-10 w-10 place-items-center rounded-lg text-red hover:bg-cream sm:grid" aria-label="Account" title="{{ auth()->check() ? 'My account' : 'Login' }}"><x-ico name="user" :size="22" /></a>
            <a href="{{ route('track') }}" class="hidden h-10 w-10 place-items-center rounded-lg text-red hover:bg-cream sm:grid" aria-label="Track order" title="Track order"><x-ico name="truck" :size="22" /></a>
            <a href="{{ route('account.wishlist') }}" class="relative hidden h-10 w-10 place-items-center rounded-lg text-red hover:bg-cream md:grid" aria-label="Wishlist" title="Wishlist"><x-ico name="heart" :size="22" /><span x-show="$store.wishlist.count" x-cloak class="absolute right-0.5 top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-gold px-1 text-[0.625rem] font-bold text-maroon-deep" x-text="$store.wishlist.count"></span></a>
            <button type="button" @click="$store.ui.openCart()" class="relative grid h-10 w-10 place-items-center rounded-lg text-red hover:bg-cream" aria-label="Open cart" title="Cart"><x-ico name="bag" :size="22" /><span x-show="$store.cart.count" x-cloak class="absolute right-0 top-0 grid h-4 min-w-4 place-items-center rounded-full bg-red px-1 text-[0.625rem] font-bold text-white" x-text="$store.cart.count"></span></button>
        </div>
    </div>

    {{-- Centred navigation (desktop) --}}
    <nav class="hidden border-t border-line-soft bg-cream/60 lg:block" aria-label="Main navigation" @mouseleave="scheduleClose()">
        <div class="h-container flex items-center justify-center gap-8">
            @if(!empty($h['nav_featured_label']))<a href="{{ $h['nav_featured_url'] ?? '/shop/sale' }}" @mouseenter="scheduleClose()" class="nav-link text-red">{{ $h['nav_featured_label'] }}</a>@endif
            <a href="{{ route('shop.index') }}" @mouseenter="scheduleClose()" class="nav-link">{{ $h['nav_all_label'] ?? 'All Products' }}</a>
            <a href="{{ route('shop.index') }}" @mouseenter="openMega('categories')" class="nav-link flex items-center gap-1.5" :class="mega === 'categories' && 'is-open'">{{ $h['nav_category_label'] ?? 'Shop By Category' }} <x-ico name="chevron-down" :size="14" class="text-gold" /></a>
            @if($needs->isNotEmpty())<a href="{{ route('collections.index') }}" @mouseenter="openMega('needs')" class="nav-link flex items-center gap-1.5" :class="mega === 'needs' && 'is-open'">{{ $h['nav_need_label'] ?? 'Shop By Need' }} <x-ico name="chevron-down" :size="14" class="text-gold" /></a>@endif
            @foreach($links->take(3) as $item)
                <a href="{{ $item->href }}" @mouseenter="scheduleClose()" @if($item->opens_in_new_tab) target="_blank" rel="noopener" @endif class="nav-link {{ $loop->index >= 2 ? 'hidden xl:block' : '' }} {{ $item->is_accent ? 'text-red' : '' }}">{{ $item->label }}</a>
            @endforeach
        </div>

        <div x-show="mega !== null" x-cloak x-transition.opacity.duration.150ms @mouseenter="openMega(mega)" class="absolute inset-x-0 top-full border-t border-gold/40 bg-warm shadow-[0_30px_50px_-30px_rgba(41,35,31,.45)]">
            {{-- Shop by category: multi-level columns --}}
            <div x-show="mega === 'categories'" class="h-container grid grid-cols-4 gap-x-8 gap-y-6 py-8 xl:grid-cols-5">
                @foreach($cats as $c)
                    <div>
                        <a href="{{ $c->url }}" class="flex items-center gap-2 font-serif text-[1rem] font-semibold text-maroon hover:text-red">{{ $c->name }}</a>
                        @if($c->children->isNotEmpty())
                            <ul class="mt-2 space-y-1.5 border-l border-line pl-3">@foreach($c->children as $sub)<li><a href="{{ $sub->url }}" class="text-[0.8125rem] text-muted hover:text-red">{{ $sub->name }}</a></li>@endforeach</ul>
                        @else
                            <p class="mt-1 text-[0.8125rem] text-muted">{{ $c->tagline }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
            {{-- Shop by need --}}
            <div x-show="mega === 'needs'" class="h-container grid grid-cols-3 gap-x-8 gap-y-4 py-8 xl:grid-cols-4">
                @foreach($needs as $n)
                    <a href="{{ route('collections.show', $n->slug) }}" class="rounded-lg p-3 hover:bg-cream"><span class="block font-serif text-[1rem] font-semibold text-maroon">{{ $n->name }}</span><span class="mt-1 line-clamp-2 block text-[0.8125rem] text-muted">{{ $n->description }}</span></a>
                @endforeach
            </div>
        </div>
    </nav>

    {{-- Category chips (mobile) --}}
    <div class="no-scrollbar flex gap-2 overflow-x-auto border-t border-line-soft px-4 py-2 lg:hidden">
        <button type="button" @click="$store.ui.openMenu()" class="chip chip-active"><x-ico name="grid" :size="14" /> All</button>
        @foreach($cats->take(10) as $c)<a href="{{ $c->url }}" class="chip">{{ $c->name }}</a>@endforeach
    </div>
</header>
