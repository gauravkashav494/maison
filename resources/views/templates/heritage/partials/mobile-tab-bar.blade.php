{{-- App-style bottom tabs (phones/tablets). Home · Categories · Search · Cart · Account --}}
@php
    $tab = fn (bool $active) => 'tab-item '.($active ? 'is-active text-red' : 'text-muted');
    $onShop = request()->is('shop*') || request()->is('product/*') || request()->is('collections*');
    $onAccount = request()->is('account*') || request()->is('login') || request()->is('register');
@endphp
<nav x-data class="tab-bar border-t border-line bg-warm/95 backdrop-blur lg:hidden" aria-label="Quick navigation">
    <ul class="grid grid-cols-5">
        <li><a href="{{ route('home') }}" class="{{ $tab(request()->routeIs('home')) }}" @if(request()->routeIs('home')) aria-current="page" @endif><span class="tab-ico"><x-ico name="home" :size="23" :stroke="request()->routeIs('home') ? 2.2 : 1.7" /></span>Home</a></li>
        <li><button type="button" @click="$store.ui.openMenu()" class="{{ $tab($onShop) }}"><span class="tab-ico"><x-ico name="grid" :size="23" :stroke="$onShop ? 2.2 : 1.7" /></span>Categories</button></li>
        <li><button type="button" @click="$store.ui.openSearch()" class="{{ $tab(request()->is('search*')) }}"><span class="tab-ico"><x-ico name="search" :size="23" :stroke="1.7" /></span>Search</button></li>
        <li><button type="button" @click="$store.ui.openCart()" class="{{ $tab(request()->is('cart*') || request()->is('checkout*')) }}"><span class="tab-ico"><x-ico name="bag" :size="23" :stroke="1.7" /></span>Cart<span x-show="$store.cart.count" x-cloak class="tab-badge bg-red text-white" x-text="$store.cart.count"></span></button></li>
        <li><a href="{{ auth()->check() ? route('account.index') : route('login') }}" class="{{ $tab($onAccount) }}" @if($onAccount) aria-current="page" @endif><span class="tab-ico"><x-ico name="user" :size="23" :stroke="$onAccount ? 2.2 : 1.7" /></span>Account</a></li>
    </ul>
</nav>
