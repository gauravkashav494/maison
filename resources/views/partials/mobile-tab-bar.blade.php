@php $item = 'flex flex-1 flex-col items-center gap-1 py-2 text-[0.5625rem] uppercase tracking-[0.15em]'; @endphp
<nav x-data class="fixed inset-x-0 bottom-0 z-40 border-t border-ink/10 bg-ivory/95 pb-safe backdrop-blur-md lg:hidden" aria-label="Mobile">
    <div class="flex">
        <a href="{{ route('home') }}" class="{{ $item }} {{ request()->is('/') ? 'text-ink' : 'text-smoke' }}"><x-ico name="home" :size="19" /> Home</a>
        <a href="{{ route('shop.index') }}" class="{{ $item }} {{ request()->is('shop*') ? 'text-ink' : 'text-smoke' }}"><x-ico name="grid" :size="19" /> Shop</a>
        <button type="button" @click="$store.ui.openSearch()" class="{{ $item }} text-smoke"><x-ico name="search" :size="19" /> Search</button>
        <a href="{{ route('account.wishlist') }}" class="{{ $item }} text-smoke"><x-ico name="heart" :size="19" /> Wishlist</a>
        <button type="button" @click="$store.ui.openCart()" class="{{ $item }} relative text-smoke">
            <span class="relative">
                <x-ico name="bag" :size="19" />
                <span x-cloak x-show="$store.cart.count > 0" x-text="$store.cart.count" class="absolute -right-2 -top-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-ink px-1 text-[0.5625rem] text-ivory"></span>
            </span>
            Bag
        </button>
    </div>
</nav>
