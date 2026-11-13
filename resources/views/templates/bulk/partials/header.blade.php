{{--
    Store header: announcement, brand bar (logo, product search, trade desk, account, cart) and
    the category bar with the Bulk order link at its head. $navCategories comes from
    BulkTemplate::viewData(). A bare x-data gives Alpine a scope for the cart count.
--}}
@php
    $storeName = setting('site.name', config('app.name'));
    $phone = tsetting('site.support_phone');
@endphp

<header x-data>
    @if($announcement = tsetting('site.announcement'))
        <div class="bk-announce">
            <div class="bk-wrap flex items-center justify-center gap-2 py-2 text-center">
                <x-heroicon-o-truck class="hidden size-4 sm:block" aria-hidden="true" />
                <span>{{ $announcement }}</span>
            </div>
        </div>
    @endif

    <div class="bk-top">
        <div class="bk-wrap flex flex-wrap items-center gap-x-4 gap-y-3 py-3 lg:flex-nowrap lg:py-4">
            <a href="{{ url('/') }}" class="bk-logo order-1">
                <span class="bk-logo-mark"><x-heroicon-o-cube aria-hidden="true" /></span>
                <span>{{ $storeName }}<small>Trade supplies</small></span>
            </a>

            <form method="get" action="{{ route('search') }}" role="search" class="bk-search order-3 w-full lg:order-2 lg:mx-4 lg:w-auto">
                <label class="sr-only" for="bk-search">Search products</label>
                <input id="bk-search" type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}"
                       placeholder="Search products, brands or codes…" autocomplete="off">
                <button type="submit" aria-label="Search"><x-heroicon-o-magnifying-glass /></button>
            </form>

            <div class="order-2 ml-auto flex items-center gap-1 lg:order-3 lg:ml-0">
                @if($phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="bk-top-action hidden xl:inline-flex">
                        <x-heroicon-o-phone aria-hidden="true" />
                        <span><span class="opacity-75">Trade desk</span><strong>{{ $phone }}</strong></span>
                    </a>
                @endif
                <a href="{{ auth()->check() ? route('account.orders') : route('login') }}" class="bk-top-action">
                    <x-heroicon-o-user aria-hidden="true" />
                    <span class="hidden sm:block">
                        <span class="opacity-75">{{ auth()->check() ? 'Hello, '.\Illuminate\Support\Str::before(auth()->user()->name, ' ') : 'Sign in' }}</span>
                        <strong>{{ auth()->check() ? 'My orders' : 'Account' }}</strong>
                    </span>
                </a>
                <button type="button" class="bk-top-action bk-cart-pill" @click="$store.ui.openCart()" aria-label="Open your order">
                    <x-heroicon-o-shopping-cart aria-hidden="true" />
                    <span class="bk-cart-count" x-text="$store.cart.count">0</span>
                    <span class="hidden text-left sm:block">
                        <span class="opacity-75">Your order</span>
                        <strong x-text="$store.cart.subtotal_formatted">₹0</strong>
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{--
        Category bar. On desktop, catBar (resources/templates/bulk/js/nav.js) measures the room
        and shows as many categories as fit; the rest go under "More". Phones scroll the whole
        row instead. Without JavaScript every category is shown inline.
    --}}
    @php
        $navCategories = collect($navCategories ?? []);
        $activeCategory = $navCategories->search(fn ($c) => request()->is('shop/'.$c->slug));
    @endphp
    <nav class="bk-catbar" aria-label="Categories">
        <div class="bk-wrap bk-catbar-row" x-data="catBar" x-ref="row">
            <div class="bk-catbar-main" x-ref="main">
                <a href="{{ url('/#bulk-order') }}" class="is-cta" data-fixed><x-heroicon-o-clipboard-document-list aria-hidden="true" />Bulk order</a>
                <a href="{{ route('shop.index') }}" @class(['is-on' => request()->routeIs('shop.index')]) data-fixed>All products</a>
                @foreach($navCategories as $cat)
                    <a href="{{ $cat->url }}" @class(['is-on' => $activeCategory === $loop->index]) data-cat
                       x-show="{{ $loop->index }} < fit">{{ $cat->name }}</a>
                @endforeach
            </div>
            @if($navCategories->isNotEmpty())
                <div class="bk-catmore hidden lg:block" x-show="fit < {{ $navCategories->count() }}" x-cloak
                     x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="true"
                            :class="{{ $activeCategory === false ? 'false' : $activeCategory.' >= fit' }} && 'is-on'">
                        More <x-heroicon-m-chevron-down aria-hidden="true" />
                    </button>
                    <div class="bk-catmore-menu" x-show="open" x-cloak x-transition.opacity.duration.150ms>
                        @foreach($navCategories as $cat)
                            <a href="{{ $cat->url }}" @class(['is-on' => $activeCategory === $loop->index]) x-show="{{ $loop->index }} >= fit">{{ $cat->name }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="bk-catbar-side" x-ref="side">
                @foreach($menus['header'] ?? [] as $item)
                    {{-- A link is current when its path is the page's path (or a page under it), on this host. --}}
                    @php
                        $linkHost = parse_url($item->url, PHP_URL_HOST);
                        $linkPath = trim((string) parse_url($item->url, PHP_URL_PATH), '/');
                        $current = $linkPath !== '' && (! $linkHost || $linkHost === request()->getHost())
                            && (request()->is($linkPath) || request()->is($linkPath.'/*'));
                    @endphp
                    <a href="{{ $item->url }}" @class(['is-on' => $current]) @if($current) aria-current="page" @endif>{{ $item->label }}</a>
                @endforeach
            </div>
        </div>
    </nav>
</header>
