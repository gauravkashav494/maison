{{-- Slide-in cart, mirrored from the server session via the Alpine cart store --}}
<div x-data x-show="$store.ui.cartOpen" x-cloak>
    <div class="overlay" x-show="$store.ui.cartOpen" x-transition.opacity @click="$store.ui.closeAll()"></div>
    <aside class="sheet sheet-right" x-show="$store.ui.cartOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" x-trap.noscroll="$store.ui.cartOpen" aria-label="Shopping cart">
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
            <h2 class="font-serif text-xl">Your cart <span class="text-sm text-muted" x-text="$store.cart.count ? '(' + $store.cart.count + ')' : ''"></span></h2>
            <button type="button" @click="$store.ui.closeAll()" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-cream" aria-label="Close"><x-ico name="close" :size="20" /></button>
        </div>
        <div x-show="$store.cart.count" class="space-y-2 border-b border-line bg-cream px-5 py-3">
            <p x-show="$store.cart.savings > 0" class="text-xs font-semibold text-maroon">✦ You are saving <span x-text="$store.cart.savings_formatted"></span> on this order</p>
            <template x-if="$store.cart.free_shipping_threshold > 0">
                <div><p class="text-xs text-muted" x-text="$store.cart.remaining > 0 ? 'Add ' + $store.cart.remaining_formatted + ' more for free delivery' : 'Free delivery unlocked'"></p><div class="mt-1.5 h-1 overflow-hidden rounded-full bg-line"><div class="h-full rounded-full bg-gold transition-all duration-500" :style="`width: ${$store.cart.progress * 100}%`"></div></div></div>
            </template>
        </div>
        <div class="flex-1 overflow-y-auto">
            <template x-if="$store.cart.loaded && !$store.cart.count">
                <div class="flex h-full flex-col items-center justify-center gap-3 p-8 text-center">
                    <span class="grid h-20 w-20 place-items-center rounded-full border border-gold bg-cream text-gold"><x-ico name="bag" :size="32" :stroke="1.4" /></span>
                    <p class="font-serif text-xl">Your cart is empty</p>
                    <p class="text-sm text-muted">Fill it with staples, spices and something sweet.</p>
                    <button type="button" @click="$store.ui.closeAll()" class="btn btn-primary mt-2">Start shopping</button>
                </div>
            </template>
            <ul class="divide-y divide-line-soft px-5">
                <template x-for="item in $store.cart.items" :key="item.key">
                    <li class="flex gap-3 py-4">
                        <a :href="item.product.url" class="h-[4.5rem] w-[4.5rem] shrink-0 overflow-hidden rounded-lg border border-line-soft bg-cream"><img :src="item.product.images[0]" alt="" class="img-cover"></a>
                        <div class="min-w-0 flex-1">
                            <a :href="item.product.url" class="line-clamp-2 text-[0.8125rem] font-semibold leading-snug hover:text-red" x-text="item.product.name"></a>
                            <p class="mt-0.5 text-xs text-muted" x-text="item.size"></p>
                            <div class="mt-2 flex items-center justify-between">
                                <div class="text-sm font-semibold tabular"><span x-text="item.line_total_formatted"></span> <span x-show="item.product.compare_at_price" class="ml-1 text-xs font-normal text-muted strike" x-text="'₹' + (item.product.compare_at_price * item.qty).toLocaleString('en-IN')"></span></div>
                                <div class="stepper !h-8"><button type="button" class="!w-8" @click="$store.cart.update(item.key, item.qty - 1)" aria-label="Decrease"><x-ico name="minus" :size="14" /></button><span class="!min-w-7 text-xs" x-text="item.qty"></span><button type="button" class="!w-8" @click="$store.cart.update(item.key, item.qty + 1)" :disabled="item.product.max_qty && item.qty >= item.product.max_qty" aria-label="Increase"><x-ico name="plus" :size="14" /></button></div>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
        <div x-show="$store.cart.count" class="border-t border-line p-5 safe-bottom">
            <dl class="space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd class="tabular" x-text="$store.cart.subtotal_formatted"></dd></div>
                <div class="flex justify-between" x-show="$store.cart.discount > 0"><dt class="text-muted">Coupon</dt><dd class="tabular text-leaf" x-text="'− ' + $store.cart.discount_formatted"></dd></div>
                <div class="flex justify-between"><dt class="text-muted">Delivery</dt><dd class="tabular" x-text="$store.cart.shipping === 0 ? 'Free' : $store.cart.shipping_formatted"></dd></div>
                <div class="flex justify-between border-t border-line pt-2 font-serif text-lg"><dt>Total</dt><dd class="tabular font-semibold" x-text="$store.cart.total_formatted"></dd></div>
            </dl>
            <a href="{{ route('checkout') }}" class="btn btn-primary btn-lg btn-block mt-4">Checkout <x-ico name="arrow-right" :size="16" /></a>
            <a href="{{ route('cart') }}" class="mt-2 block text-center text-sm font-medium text-red underline-offset-4 hover:underline">View cart</a>
        </div>
    </aside>
</div>
