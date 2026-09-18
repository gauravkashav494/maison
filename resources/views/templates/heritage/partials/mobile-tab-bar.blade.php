{{-- App-style bottom tabs (phones/tablets): Home · Shop/Filter · Search · Wishlist · Account. On product listings the second tab opens the filter sheet. --}}
@php
    $tab = fn (bool $on) => 'tab-item '.($on ? 'is-active text-red' : 'text-ink');
    $onListing = request()->is('shop') || request()->is('shop/*');
    $onShop = $onListing || request()->is('product/*') || request()->is('collections*');
    $onAccount = request()->is('account*') && ! request()->is('account/wishlist') || request()->is('login') || request()->is('register');
@endphp
<nav x-data class="tab-bar border-t border-line bg-white/95 backdrop-blur lg:hidden" aria-label="Quick navigation">
    <ul class="grid grid-cols-5">
        <li><a href="{{ route('home') }}" class="{{ $tab(request()->routeIs('home')) }}" @if(request()->routeIs('home')) aria-current="page" @endif><span class="tab-ico"><x-ico name="home" :size="18" :stroke="1.7" /></span>Home</a></li>
        @if($onListing)
            <li><button type="button" @click="window.dispatchEvent(new CustomEvent('open-filters'))" class="{{ $tab(false) }}"><span class="tab-ico"><x-ico name="filter" :size="18" :stroke="1.7" /></span>Filter</button></li>
        @else
            <li><button type="button" @click="$store.ui.openMenu()" class="{{ $tab($onShop) }}"><span class="tab-ico"><x-ico name="grid" :size="18" :stroke="1.7" /></span>Categories</button></li>
        @endif
        <li><button type="button" @click="$store.ui.openSearch()" class="{{ $tab(request()->is('search*')) }}"><span class="tab-ico"><x-ico name="search" :size="18" :stroke="1.7" /></span>Search</button></li>
        <li><a href="{{ route('account.wishlist') }}" class="{{ $tab(request()->is('account/wishlist')) }}" @if(request()->is('account/wishlist')) aria-current="page" @endif><span class="tab-ico"><x-ico name="heart" :size="18" :stroke="1.7" /></span>Wishlist<span x-show="$store.wishlist.count" x-cloak class="tab-badge bg-red text-white" x-text="$store.wishlist.count"></span></a></li>
        <li><a href="{{ auth()->check() ? route('account.index') : route('login') }}" class="{{ $tab($onAccount) }}" @if($onAccount) aria-current="page" @endif><span class="tab-ico"><x-ico name="user" :size="18" :stroke="1.7" /></span>Account</a></li>
    </ul>
</nav>
