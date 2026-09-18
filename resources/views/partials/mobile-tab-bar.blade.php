{{-- App-style bottom tabs (phones/tablets). Home · Shop · Search · Wishlist · Bag --}}
@php
    $tab = fn (bool $active) => 'tab-item '.($active ? 'is-active text-ink' : 'text-smoke');
    $onShop = request()->is('shop*') || request()->is('product/*') || request()->is('collections*');
@endphp
<nav x-data class="tab-bar border-t border-ink/10 bg-ivory/95 backdrop-blur-md lg:hidden" aria-label="Mobile">
    <div class="flex">
        <a href="{{ route('home') }}" class="{{ $tab(request()->routeIs('home')) }}" @if(request()->routeIs('home')) aria-current="page" @endif><span class="tab-ico"><x-ico name="home" :size="22" :stroke="request()->routeIs('home') ? 1.9 : 1.5" /></span>Home</a>
        <a href="{{ route('shop.index') }}" class="{{ $tab($onShop) }}" @if($onShop) aria-current="page" @endif><span class="tab-ico"><x-ico name="grid" :size="22" :stroke="$onShop ? 1.9 : 1.5" /></span>Shop</a>
        <button type="button" @click="$store.ui.openSearch()" class="{{ $tab(false) }}"><span class="tab-ico"><x-ico name="search" :size="22" :stroke="1.5" /></span>Search</button>
        <a href="{{ route('account.wishlist') }}" class="{{ $tab(request()->is('account/wishlist')) }}"><span class="tab-ico"><x-ico name="heart" :size="22" :stroke="request()->is('account/wishlist') ? 1.9 : 1.5" /></span>Wishlist</a>
        <button type="button" @click="$store.ui.openCart()" class="{{ $tab(request()->is('cart*') || request()->is('checkout*')) }}">
            <span class="tab-ico"><x-ico name="bag" :size="22" :stroke="1.5" /></span>Bag
            <span x-cloak x-show="$store.cart.count > 0" x-text="$store.cart.count" class="tab-badge bg-ink text-ivory"></span>
        </button>
    </div>
</nav>
