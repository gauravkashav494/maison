{{--
    Store header: announcement, brand bar (logo, product search, trade desk, account, cart) and
    the menu bar with the Bulk order link at its head. A bare x-data gives Alpine a scope for
    the cart count.
--}}
@php
    $storeName = setting('site.name', config('app.name'));
    $phone = store_phone();
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
        <div class="bk-wrap flex flex-wrap items-center gap-x-3 gap-y-3 py-3 sm:gap-x-4 lg:flex-nowrap lg:py-4">
            <a href="{{ url('/') }}" class="bk-logo order-1 min-w-0 flex-1 basis-0 lg:flex-none lg:basis-auto">
                <span class="bk-logo-mark"><x-heroicon-o-cube aria-hidden="true" /></span>
                <span class="min-w-0 truncate">{{ $storeName }}<small class="truncate">Trade supplies</small></span>
            </a>

            <form method="get" action="{{ route('search') }}" role="search" class="bk-search order-3 w-full lg:order-2 lg:mx-4 lg:w-auto">
                <label class="sr-only" for="bk-search">Search products</label>
                <input id="bk-search" type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}"
                       placeholder="Search products, brands or codes…" autocomplete="off">
                <button type="submit" aria-label="Search"><x-heroicon-o-magnifying-glass /></button>
            </form>

            <div class="order-2 ml-auto flex flex-none items-center gap-0.5 sm:gap-1 lg:order-3 lg:ml-0">
                @if($phone)
                    {{-- Just the icon until there is room for the number; it still calls the desk.
                         (A display utility on .bk-top-action itself would lose to its own display rule,
                         so only the text is hidden.) --}}
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="bk-top-action" aria-label="Call the trade desk on {{ $phone }}">
                        <x-heroicon-o-phone aria-hidden="true" />
                        <span class="hidden xl:block" aria-hidden="true"><span class="opacity-75">Trade desk</span><strong>{{ $phone }}</strong></span>
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
        Menu bar: Bulk order and All products, then the header menu links on the right. It is
        the same on every page. Categories are not repeated here: All products lists them down
        the side (chips on phones), and the footer links them too.
    --}}
    <nav class="bk-catbar" aria-label="Main">
        <div class="bk-wrap bk-catbar-row">
            <div class="bk-catbar-main">
                <a href="{{ url('/#bulk-order') }}" class="is-cta"><x-heroicon-o-clipboard-document-list aria-hidden="true" />Bulk order</a>
                <a href="{{ route('shop.index') }}" @class(['is-on' => request()->routeIs('shop.index', 'shop.category')])>All products</a>
                {{-- Ready-made order lists; no tab until the store has at least one. --}}
                @if(\App\Models\Collection::active()->exists())
                    <a href="{{ route('collections.index') }}" @class(['is-on' => request()->routeIs('collections.*')])>Collections</a>
                @endif
            </div>
            <div class="bk-catbar-side">
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
