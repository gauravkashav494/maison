{{--
    The order drawer: the shared cart store, shown as the store's own panel — a picture, name,
    code and stepper per line, a running subtotal, and the way to checkout.
--}}
<div x-data x-cloak x-show="$store.ui.cartOpen" class="fixed inset-0 z-[70]"
     x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <button type="button" class="absolute inset-0 bg-black/40" aria-label="Close your order" @click="$store.ui.closeAll()"></button>

    <aside x-show="$store.ui.cartOpen" x-trap.noscroll="$store.ui.cartOpen"
           x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-2xl"
           role="dialog" aria-modal="true" aria-label="Your order">

        <div class="bk-top flex items-center gap-3 px-5 py-4">
            <x-heroicon-o-shopping-cart class="size-6" aria-hidden="true" />
            <p class="text-lg font-extrabold">Your order</p>
            <span class="rounded-full bg-white/15 px-2 py-0.5 text-xs font-bold" x-text="$store.cart.count + ($store.cart.count === 1 ? ' unit' : ' units')"></span>
            <button type="button" class="ml-auto grid size-9 place-items-center rounded-lg hover:bg-white/10" @click="$store.ui.closeAll()" aria-label="Close">
                <x-heroicon-o-x-mark class="size-5" />
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-5">
            <div class="py-16 text-center" x-show="$store.cart.loaded && !$store.cart.items.length">
                <x-heroicon-o-clipboard-document-list class="mx-auto size-12 text-brand" aria-hidden="true" />
                <p class="mt-3 font-bold">Your order is empty</p>
                <p class="bk-muted mt-1 text-sm">Add products from the catalogue, or your whole list at once on Bulk order.</p>
                <a href="{{ url('/#bulk-order') }}" class="bk-btn bk-btn-primary mt-5" @click="$store.ui.closeAll()">Open Bulk order</a>
            </div>

            <ul class="divide-y divide-[var(--bk-line)]">
                <template x-for="item in $store.cart.items" :key="item.key">
                    <li class="flex gap-3 py-4">
                        <a :href="item.product.url" class="bk-art bk-thumb" data-tone="0">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
                            <template x-if="item.product.images && item.product.images.length"><img :src="item.product.images[0]" alt=""></template>
                        </a>
                        <div class="min-w-0 flex-1">
                            <a :href="item.product.url" class="block text-sm font-bold leading-snug hover:text-brand" x-text="item.product.name"></a>
                            <p class="bk-code mt-0.5">
                                <span x-text="item.variant_sku || ''"></span><span x-show="item.options_label" x-text="' · ' + item.options_label"></span>
                            </p>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <span class="bk-step">
                                    <button type="button" @click="$store.cart.update(item.key, item.qty - 1)" aria-label="Decrease"><x-heroicon-o-minus /></button>
                                    <input type="number" min="0" :value="item.qty" @change="$store.cart.update(item.key, parseInt($event.target.value, 10) || 0)" aria-label="Quantity">
                                    <button type="button" @click="$store.cart.update(item.key, item.qty + 1)" aria-label="Increase"><x-heroicon-o-plus /></button>
                                </span>
                                <span class="text-right">
                                    <span class="block font-extrabold bk-num" x-text="item.line_total_formatted"></span>
                                    <button type="button" class="text-xs font-semibold text-[var(--bk-out)] hover:underline" @click="$store.cart.remove(item.key)">Remove</button>
                                </span>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>

        <div class="border-t border-[var(--bk-line)] bg-[var(--bk-page)] p-5" x-show="$store.cart.items.length">
            <p class="flex justify-between text-base font-extrabold"><span>Subtotal</span><span class="bk-num" x-text="$store.cart.subtotal_formatted"></span></p>
            <p class="bk-muted mt-1 text-xs">Delivery and GST are worked out at checkout.</p>
            <a href="{{ route('checkout') }}" class="bk-btn bk-btn-primary bk-btn-block mt-4">Checkout</a>
            <a href="{{ route('cart') }}" class="bk-btn bk-btn-ghost bk-btn-block mt-1">Review full order</a>
        </div>
    </aside>
</div>
