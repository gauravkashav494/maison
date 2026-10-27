{{--
    The order panel for the Simple List template: the same shared cart store as everywhere
    else, shown as lines of text. No thumbnails, because this template's products have no
    imagery at all.
--}}
<div x-data x-cloak x-show="$store.ui.cartOpen" class="fixed inset-0 z-[70]"
     x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <button type="button" aria-label="Close order" class="absolute inset-0" style="background:rgb(0 0 0 / .35)" @click="$store.ui.closeAll()"></button>

    <aside x-show="$store.ui.cartOpen" x-trap.noscroll="$store.ui.cartOpen"
           x-transition:enter="transition duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="absolute inset-y-0 right-0 flex w-full max-w-[28rem] flex-col bg-white"
           role="dialog" aria-modal="true" aria-label="Your order">

        <div class="ol-bar">
            <div class="ol-bar-inner" style="padding-inline:1rem">
                <strong>Your order (<span x-text="$store.cart.count">0</span>)</strong>
                <button type="button" class="ol-choose" style="margin-left:auto" @click="$store.ui.closeAll()">Close</button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto" style="padding:0 1rem">
            <p class="ol-empty" x-show="$store.cart.loaded && !$store.cart.items.length">
                Nothing in the order yet. Add products from the list.
            </p>

            <table class="ol-table" x-show="$store.cart.items.length" style="margin-top:1rem">
                <tbody>
                    <template x-for="item in $store.cart.items" :key="item.key">
                        <tr>
                            <td>
                                <span class="ol-name"><a :href="item.product.url" x-text="item.product.name"></a></span>
                                <span class="ol-sub">
                                    <span class="ol-code" x-text="item.variant_sku || ''"></span>
                                    <span x-show="item.options_label" x-text="' · ' + item.options_label"></span>
                                </span>
                                <span class="ol-qty" style="margin-top:.4rem">
                                    <button type="button" @click="$store.cart.update(item.key, item.qty - 1)" aria-label="Decrease">−</button>
                                    <input type="number" min="0" :value="item.qty" @change="$store.cart.update(item.key, parseInt($event.target.value, 10) || 0)" aria-label="Quantity">
                                    <button type="button" @click="$store.cart.update(item.key, item.qty + 1)" aria-label="Increase">+</button>
                                </span>
                            </td>
                            <td class="ol-price">
                                <span x-text="item.line_total_formatted"></span>
                                <span class="ol-sub"><button type="button" class="ol-choose" @click="$store.cart.remove(item.key)">Remove</button></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="$store.cart.items.length" style="border-top:1px solid var(--ol-line);padding:1rem">
            <p style="display:flex;justify-content:space-between;font-size:.9375rem">
                <span>Subtotal</span><span class="ol-price" x-text="$store.cart.subtotal_formatted"></span>
            </p>
            <p class="ol-note" style="margin-top:.25rem">Shipping and tax are calculated at checkout.</p>
            <a href="{{ route('checkout') }}" class="ol-add" style="display:block;margin-top:.75rem;text-align:center;text-decoration:none">Go to checkout</a>
            <a href="{{ route('cart') }}" class="ol-choose" style="display:block;margin-top:.6rem;text-align:center">View the full order</a>
        </div>
    </aside>
</div>
