<div x-data x-cloak x-show="$store.ui.cartOpen" class="fixed inset-0 z-[70]"
     x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <button type="button" aria-label="Close bag" class="absolute inset-0 bg-ink/40" @click="$store.ui.closeAll()"></button>
    <aside x-show="$store.ui.cartOpen" x-trap.noscroll="$store.ui.cartOpen"
           x-transition:enter="transition duration-500 ease-[var(--ease-luxe)]" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition duration-400 ease-[var(--ease-luxe)]" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="absolute inset-y-0 right-0 flex w-full max-w-[30rem] flex-col bg-ivory text-ink" role="dialog" aria-modal="true" aria-label="Shopping bag">
        <div class="flex h-[4.5rem] items-center justify-between border-b border-ink/10 px-6">
            <h2 class="font-serif text-2xl">Shopping Bag <span class="ml-1 text-sm text-smoke tabular-nums">(<span x-text="$store.cart.count"></span>)</span></h2>
            <button type="button" @click="$store.ui.closeAll()" aria-label="Close" class="-mr-2 grid h-10 w-10 place-items-center"><x-ico name="close" :size="20" /></button>
        </div>

        {{-- Free shipping progress --}}
        <div class="border-b border-ink/10 px-6 py-4" x-show="$store.cart.free_shipping_threshold > 0">
            <p class="text-[0.75rem] text-smoke">
                <template x-if="$store.cart.remaining > 0"><span>You are <span class="text-ink" x-text="$store.cart.remaining_formatted"></span> away from complimentary shipping</span></template>
                <template x-if="$store.cart.remaining <= 0"><span class="text-ink">Your order qualifies for complimentary shipping</span></template>
            </p>
            <div class="mt-2.5 h-px w-full bg-ink/10"><div class="h-px bg-gold transition-[width] duration-700 ease-[var(--ease-luxe)]" :style="`width:${Math.min(100, ($store.cart.progress || 0) * 100)}%`"></div></div>
        </div>

        <div class="flex-1 overflow-y-auto px-6">
            {{-- Empty --}}
            <div x-show="$store.cart.loaded && !$store.cart.items.length" class="py-14 text-center">
                <p class="font-serif text-2xl">Your bag is empty</p>
                <p class="mt-2 text-sm text-smoke">Discover the new season and the pieces that define it.</p>
                <a href="{{ route('shop.index') }}" class="btn btn-outline mt-8" @click="$store.ui.closeAll()">Continue shopping</a>
            </div>

            {{-- Items --}}
            <ul class="divide-y divide-ink/10">
                <template x-for="item in $store.cart.items" :key="item.key">
                    <li class="flex gap-4 py-5">
                        <a :href="item.product.url" class="relative aspect-[3/4] w-24 shrink-0 overflow-hidden bg-sand"><img :src="item.product.images[0]" :alt="item.product.name" class="img-cover"></a>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[0.5625rem] uppercase tracking-[0.2em] text-taupe" x-text="item.product.category"></p>
                                    <p class="mt-1 font-serif text-base leading-tight" x-text="item.product.name"></p>
                                    <p class="mt-1 text-[0.6875rem] text-smoke" x-text="(item.color ? item.color + ' · ' : '') + item.size"></p>
                                </div>
                                <p class="shrink-0 text-sm tabular-nums" x-text="item.line_total_formatted"></p>
                            </div>
                            <div class="mt-auto flex items-center justify-between pt-3">
                                <div class="flex h-8 items-center border border-ink/15">
                                    <button type="button" @click="$store.cart.update(item.key, item.qty - 1)" aria-label="Decrease" class="grid h-full w-8 place-items-center hover:bg-sand"><x-ico name="minus" :size="12" :stroke="1.5" /></button>
                                    <span class="w-7 text-center text-xs tabular-nums" x-text="item.qty"></span>
                                    <button type="button" @click="$store.cart.update(item.key, item.qty + 1)" aria-label="Increase" class="grid h-full w-8 place-items-center hover:bg-sand"><x-ico name="plus" :size="12" :stroke="1.5" /></button>
                                </div>
                                <div class="flex items-center gap-3 text-[0.625rem] uppercase tracking-widest text-smoke">
                                    <button type="button" @click="$store.wishlist.toggle(item.product.id); $store.cart.remove(item.key)" class="flex items-center gap-1 hover:text-ink"><x-ico name="heart" :size="11" :stroke="1.5" /> Save</button>
                                    <button type="button" @click="$store.cart.remove(item.key)" class="hover:text-ink">Remove</button>
                                </div>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>

            {{-- Recommendations --}}
            <div x-show="$store.cart.recommendations.length" class="border-t border-ink/10 py-6">
                <p class="eyebrow mb-4 text-taupe">You may also like</p>
                <ul class="space-y-4">
                    <template x-for="p in $store.cart.recommendations" :key="p.id">
                        <li class="flex items-center gap-4">
                            <div class="relative h-20 w-16 shrink-0 overflow-hidden bg-sand"><img :src="p.images[0]" :alt="p.name" class="img-cover"></div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-serif text-[0.9375rem]" x-text="p.name"></p>
                                <p class="text-xs tabular-nums text-smoke" x-text="p.price_formatted"></p>
                            </div>
                            <button type="button" @click="$store.ui.showQuickView(p.slug)" class="border border-ink/20 px-3 py-2 text-[0.5625rem] uppercase tracking-[0.18em] transition-colors hover:bg-ink hover:text-ivory">Add</button>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        <div x-show="$store.cart.items.length" class="border-t border-ink/10 px-6 pb-6 pt-5">
            <div class="flex items-center justify-between text-sm"><span>Subtotal</span><span class="tabular-nums" x-text="$store.cart.subtotal_formatted"></span></div>
            <p class="mt-1 text-[0.6875rem] text-smoke">Shipping and taxes calculated at checkout.</p>
            <a href="{{ route('checkout') }}" class="btn btn-primary btn-lg mt-5 w-full"><x-ico name="lock" :size="12" :stroke="1.5" /> Proceed to Checkout</a>
            <a href="{{ route('cart') }}" class="link-underline mt-4 block text-center text-[0.6875rem] uppercase tracking-[0.2em]">View bag</a>
        </div>
    </aside>
</div>
