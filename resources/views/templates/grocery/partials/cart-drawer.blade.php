{{-- Slide-in cart. Mirrors the server session cart via the Alpine cart store. --}}
<div x-data x-show="$store.ui.cartOpen" x-cloak>
    <div class="overlay" x-show="$store.ui.cartOpen" x-transition.opacity @click="$store.ui.closeAll()"></div>
    <aside class="sheet sheet-right" x-show="$store.ui.cartOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" x-trap.noscroll="$store.ui.cartOpen" aria-label="Shopping cart" x-data>
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
            <h2 class="text-lg font-extrabold">My cart <span class="text-sm font-semibold text-slate" x-text="$store.cart.count ? '(' + $store.cart.count + ')' : ''"></span></h2>
            <button type="button" @click="$store.ui.closeAll()" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-paper" aria-label="Close"><x-ico name="close" :size="20" /></button>
        </div>

        {{-- Savings + free delivery progress --}}
        <div x-show="$store.cart.count" class="space-y-2 border-b border-line bg-paper px-5 py-3">
            <p x-show="$store.cart.savings > 0" class="flex items-center gap-2 rounded-lg bg-leaf-light px-3 py-1.5 text-xs font-bold text-leaf-dark"><x-ico name="sparkle" :size="14" /> You’re saving <span x-text="$store.cart.savings_formatted"></span> on this order</p>
            <template x-if="$store.cart.free_shipping_threshold > 0">
                <div>
                    <p class="text-xs text-slate" x-text="$store.cart.remaining > 0 ? 'Add ' + $store.cart.remaining_formatted + ' more for free delivery' : 'You’ve unlocked free delivery 🎉'"></p>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-line"><div class="h-full rounded-full bg-leaf transition-all duration-500" :style="`width: ${$store.cart.progress * 100}%`"></div></div>
                </div>
            </template>
        </div>

        {{-- Items --}}
        <div class="flex-1 overflow-y-auto">
            <template x-if="$store.cart.loaded && !$store.cart.count">
                <div class="flex h-full flex-col items-center justify-center gap-3 p-8 text-center">
                    <span class="grid h-20 w-20 place-items-center rounded-full bg-paper text-mist"><x-ico name="cart" :size="34" :stroke="1.5" /></span>
                    <p class="text-lg font-extrabold">Your cart is empty</p>
                    <p class="text-sm text-slate">Add fruits, veggies, snacks and essentials to get started.</p>
                    <button type="button" @click="$store.ui.closeAll()" class="btn btn-primary mt-2">Start shopping</button>
                </div>
            </template>
            <ul class="divide-y divide-line px-5">
                <template x-for="item in $store.cart.items" :key="item.key">
                    <li class="flex gap-3 py-3">
                        <a :href="item.product.url" class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-line bg-paper"><img :src="item.product.images[0]" alt="" class="img-cover"></a>
                        <div class="min-w-0 flex-1">
                            <a :href="item.product.url" class="line-clamp-2 text-[0.8125rem] font-semibold leading-snug" x-text="item.product.name"></a>
                            <p class="mt-0.5 text-xs text-slate" x-text="item.size"></p>
                            <div class="mt-1.5 flex items-center justify-between">
                                <div class="text-sm font-bold tabular"><span x-text="item.line_total_formatted"></span> <span x-show="item.product.compare_at_price" class="ml-1 text-xs font-medium text-mist strike" x-text="'₹' + (item.product.compare_at_price * item.qty).toLocaleString('en-IN')"></span></div>
                                <div class="stepper">
                                    <button type="button" @click="$store.cart.update(item.key, item.qty - 1)" aria-label="Decrease"><x-ico name="minus" :size="14" /></button>
                                    <span x-text="item.qty"></span>
                                    <button type="button" @click="$store.cart.update(item.key, item.qty + 1)" aria-label="Increase" :disabled="item.product.max_qty && item.qty >= item.product.max_qty"><x-ico name="plus" :size="14" /></button>
                                </div>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>

            {{-- Recommendations --}}
            <template x-if="$store.cart.count && $store.cart.recommendations.length">
                <div class="border-t border-line px-5 py-4">
                    <p class="text-sm font-extrabold">You might also need</p>
                    <div class="no-scrollbar mt-3 flex gap-3 overflow-x-auto">
                        <template x-for="r in $store.cart.recommendations" :key="r.id">
                            <div class="w-32 shrink-0 rounded-xl border border-line p-2">
                                <a :href="r.url" class="block aspect-square overflow-hidden rounded-lg bg-paper"><img :src="r.images[0]" alt="" class="img-cover"></a>
                                <p class="mt-2 line-clamp-2 text-xs font-semibold leading-snug" x-text="r.name"></p>
                                <p class="text-[0.6875rem] text-slate" x-text="r.unit || ''"></p>
                                <div class="mt-1.5 flex items-center justify-between">
                                    <span class="text-xs font-bold" x-text="r.price_formatted"></span>
                                    <button type="button" @click="r.sizes.length > 1 ? $store.ui.pickPack(r) : $store.cart.add(r.id, r.sizes[0], null, 1, false)" class="rounded-md border border-leaf px-2 py-1 text-[0.6875rem] font-extrabold text-leaf hover:bg-leaf-light">ADD</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div x-show="$store.cart.count" class="border-t border-line p-5 safe-bottom">
            <dl class="space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-slate">Item total</dt><dd class="tabular" x-text="$store.cart.subtotal_formatted"></dd></div>
                <div class="flex justify-between" x-show="$store.cart.discount > 0"><dt class="text-slate">Coupon <span x-text="$store.cart.coupon ? '(' + $store.cart.coupon.code + ')' : ''"></span></dt><dd class="tabular text-leaf" x-text="'− ' + $store.cart.discount_formatted"></dd></div>
                <div class="flex justify-between"><dt class="text-slate">Delivery</dt><dd class="tabular" :class="$store.cart.shipping === 0 && 'text-leaf font-bold'" x-text="$store.cart.shipping === 0 ? 'FREE' : $store.cart.shipping_formatted"></dd></div>
                <div class="flex justify-between border-t border-line pt-2 text-base font-extrabold"><dt>To pay</dt><dd class="tabular" x-text="$store.cart.total_formatted"></dd></div>
            </dl>
            <div class="mt-4 grid grid-cols-[1fr_auto] gap-2">
                <a href="{{ route('checkout') }}" class="btn btn-primary btn-lg">Proceed to checkout <x-ico name="arrow-right" :size="16" /></a>
                <a href="{{ route('cart') }}" class="btn btn-ghost btn-lg" aria-label="View cart page"><x-ico name="receipt" :size="18" /></a>
            </div>
        </div>
    </aside>
</div>
