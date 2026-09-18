@php
    $headerItems = $menus['header'];
    $megaKeys = $headerItems->filter(fn ($i) => $i->children->isNotEmpty())->pluck('id');
@endphp
<header x-data="header({{ $transparent ? 'true' : 'false' }}, {{ $transparent && request()->routeIs('home') ? 'true' : 'false' }})"
        @mouseenter="hovered = true" @mouseleave="hovered = false; scheduleClose()"
        class="app-bar fixed inset-x-0 top-0 z-50 transition-colors duration-500"
        :class="solid ? 'bg-ivory/95 text-ink backdrop-blur-md border-b border-ink/10' : 'bg-transparent text-ivory'">

    {{-- Announcement bar — collapses on scroll --}}
    @if(!empty($site['announcement_text']))
        <div class="hidden overflow-hidden bg-ink text-ivory transition-[height] duration-500 ease-[var(--ease-luxe)] lg:block" :class="scrolled ? 'h-0' : 'h-9'">
            <div class="container-luxe flex h-9 items-center justify-center text-[0.625rem] uppercase tracking-[0.22em]">
                <span class="hidden sm:inline">{{ $site['announcement_text'] }}</span>
                <span class="sm:hidden">{{ $site['announcement_text_short'] ?? $site['announcement_text'] }}</span>
                @if(!empty($site['announcement_link_label']))
                    <span class="mx-4 hidden h-3 w-px bg-ivory/30 md:inline-block"></span>
                    <a href="{{ $site['announcement_link_url'] ?? '#' }}" class="link-underline hidden md:inline">{{ $site['announcement_link_label'] }}</a>
                @endif
            </div>
        </div>
    @endif

    {{-- Main bar --}}
    <div class="container-luxe grid grid-cols-[1fr_auto_1fr] items-center transition-[height] duration-500 ease-[var(--ease-luxe)]" :class="scrolled ? 'h-14 lg:h-16' : 'h-14 lg:h-20'">
        {{-- Left: nav (desktop) / menu (mobile) --}}
        <div class="flex items-center">
            {{-- Inner pages get a back arrow, like a native navigation bar --}}
            @unless(request()->routeIs('home'))<button type="button" @click="$store.app.back()" class="-ml-2 grid h-10 w-10 place-items-center transition-opacity hover:opacity-60 lg:hidden" aria-label="Back"><x-ico name="arrow-left" :size="22" /></button>@endunless
            <button type="button" class="grid h-10 w-10 place-items-center transition-opacity hover:opacity-60 lg:hidden {{ request()->routeIs('home') ? '-ml-2' : '' }}" @click="$store.ui.openMenu()" aria-label="Open menu">
                <x-ico name="menu" :size="22" />
            </button>
            <nav class="hidden items-center gap-8 lg:flex" aria-label="Primary">
                @foreach($headerItems as $item)
                    @php
                        $hasMega = $item->children->isNotEmpty();
                        $active = $item->href === '/' ? request()->is('/') : request()->is(ltrim($item->href, '/').'*');
                    @endphp
                    <div class="relative" @mouseenter="{{ $hasMega ? "openMega({$item->id})" : 'mega = null' }}">
                        <a href="{{ $item->href }}"
                           class="link-underline py-2 text-[0.6875rem] uppercase tracking-[0.2em]"
                           :data-active="{{ $active ? 'true' : 'false' }} || mega === {{ $item->id }}"
                           @if($item->opens_in_new_tab) target="_blank" rel="noreferrer" @endif>{{ $item->label }}</a>
                    </div>
                @endforeach
            </nav>
        </div>

        {{-- Center: wordmark --}}
        <a href="{{ route('home') }}" aria-label="{{ $site['name'] ?? config('app.name') }} — home"
           class="text-center font-serif leading-none tracking-[0.12em] transition-all duration-500"
           :class="scrolled ? 'text-[1.35rem]' : 'text-[1.6rem] lg:text-[1.85rem]'">
            {{ $site['logo_primary'] ?? 'MAISON' }} <span class="italic font-light">{{ $site['logo_accent'] ?? 'Élan' }}</span>
        </a>

        {{-- Right: actions --}}
        <div class="flex items-center justify-end gap-0.5 sm:gap-1">
            <button type="button" class="grid h-10 w-10 place-items-center transition-opacity hover:opacity-60" @click="$store.ui.openSearch()" aria-label="Search">
                <x-ico name="search" :size="19" />
            </button>
            <a href="{{ auth()->check() ? route('account.index') : route('login') }}" class="hidden h-10 w-10 place-items-center transition-opacity hover:opacity-60 sm:grid" aria-label="Account">
                <x-ico name="user" :size="19" />
            </a>
            <a href="{{ route('account.wishlist') }}" class="relative hidden h-10 w-10 place-items-center transition-opacity hover:opacity-60 sm:grid" aria-label="Wishlist">
                <x-ico name="heart" :size="19" />
                <span x-cloak x-show="$store.wishlist.count > 0" class="absolute right-1 top-1.5 h-1.5 w-1.5 rounded-full bg-gold"></span>
            </a>
            <button type="button" class="relative -mr-2 grid h-10 w-10 place-items-center transition-opacity hover:opacity-60" @click="$store.ui.openCart()" aria-label="Shopping bag">
                <x-ico name="bag" :size="19" />
                <span x-cloak x-show="$store.cart.count > 0" x-text="$store.cart.count"
                      class="absolute right-0.5 top-0.5 grid h-4 min-w-4 place-items-center rounded-full px-1 text-[0.5625rem] tabular-nums"
                      :class="solid ? 'bg-ink text-ivory' : 'bg-ivory text-ink'"></span>
            </button>
        </div>
    </div>

    {{-- Mega menus --}}
    @foreach($headerItems->filter(fn ($i) => $i->children->isNotEmpty()) as $item)
        <div x-cloak x-show="mega === {{ $item->id }}"
             x-transition:enter="transition duration-400 ease-[var(--ease-luxe)]" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 -translate-y-1"
             @mouseenter="openMega({{ $item->id }})" @mouseleave="scheduleClose()"
             class="absolute inset-x-0 top-full border-t border-ink/10 bg-ivory text-ink shadow-[0_30px_60px_-30px_rgba(21,20,18,0.25)]">
            @include('partials.mega-menu', ['item' => $item])
        </div>
    @endforeach
</header>
