<nav x-data class="fixed inset-x-0 bottom-0 z-50 border-t border-line bg-white/95 backdrop-blur lg:hidden safe-bottom" aria-label="Quick navigation">
    <ul class="grid grid-cols-5 text-[0.625rem] font-semibold text-slate">
        <li><a href="{{ route('home') }}" class="flex flex-col items-center gap-1 py-2 {{ request()->routeIs('home') ? 'text-leaf' : '' }}"><x-ico name="home" :size="22" /> Home</a></li>
        <li><button type="button" @click="$store.ui.openCategories()" class="flex w-full flex-col items-center gap-1 py-2"><x-ico name="grid" :size="22" /> Categories</button></li>
        <li><button type="button" @click="$store.ui.openSearch()" class="flex w-full flex-col items-center gap-1 py-2"><x-ico name="search" :size="22" /> Search</button></li>
        <li>
            <button type="button" @click="$store.ui.openCart()" class="relative flex w-full flex-col items-center gap-1 py-2">
                <x-ico name="cart" :size="22" />
                <span x-show="$store.cart.count" x-cloak class="absolute left-1/2 top-1 grid h-4 min-w-4 -translate-x-[-6px] place-items-center rounded-full bg-saffron px-1 text-[0.5625rem] font-extrabold text-white" x-text="$store.cart.count"></span>
                Cart
            </button>
        </li>
        <li><a href="{{ auth()->check() ? route('account.index') : route('login') }}" class="flex flex-col items-center gap-1 py-2 {{ request()->is('account*') ? 'text-leaf' : '' }}"><x-ico name="user" :size="22" /> Account</a></li>
    </ul>
</nav>
