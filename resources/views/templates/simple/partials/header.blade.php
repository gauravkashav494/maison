{{-- Text-only bar: name, the list's own links, account and the cart count. --}}
{{-- A bare x-data is enough — this bar has no state of its own, but without a scope Alpine
     leaves the order button and its count untouched, so neither would ever work. --}}
<header class="ol-bar" x-data>
    <div class="ol-bar-inner">
        <a href="{{ url('/') }}" class="ol-brand">{{ setting('site.name', config('app.name')) }}</a>

        <nav class="ol-nav" aria-label="Main">
            @foreach($menus['header'] ?? [] as $item)
                <a href="{{ $item->url }}">{{ $item->label }}</a>
            @endforeach
        </nav>

        <div class="ol-bar-actions">
            <a href="{{ auth()->check() ? route('account.index') : route('login') }}">{{ auth()->check() ? 'My account' : 'Sign in' }}</a>
            <button type="button" @click="$store.ui.openCart()" aria-label="Open order">
                Order (<span class="ol-cart-count" x-text="$store.cart.count">0</span>)
            </button>
        </div>
    </div>
</header>
