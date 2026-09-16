@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

@section('content')
    <x-page-hero eyebrow="Your selection" title="Shopping Bag" :breadcrumbs="['Shopping Bag' => null]" />

    <section class="container-luxe py-12 lg:py-16" x-data="{ code: '' }">
        <div x-show="$store.cart.loaded && !$store.cart.items.length" x-cloak class="py-16 text-center">
            <p class="font-serif text-3xl">Your bag is empty</p>
            <p class="mt-3 text-sm text-smoke">Discover the new season and the pieces that define it.</p>
            <a href="{{ route('shop.index') }}" class="btn btn-outline btn-lg mt-8">Continue shopping</a>
        </div>

        <div x-show="$store.cart.items.length" x-cloak class="grid gap-12 lg:grid-cols-12 lg:gap-16">
            <div class="lg:col-span-8">
                {{-- Free shipping progress --}}
                <div class="border border-ink/10 bg-cream px-5 py-4" x-show="$store.cart.free_shipping_threshold > 0">
                    <p class="text-[0.8125rem] text-smoke">
                        <template x-if="$store.cart.remaining > 0"><span>Add <span class="text-ink" x-text="$store.cart.remaining_formatted"></span> more for complimentary standard delivery.</span></template>
                        <template x-if="$store.cart.remaining <= 0"><span class="text-ink">Your order qualifies for complimentary standard delivery.</span></template>
                    </p>
                    <div class="mt-2.5 h-px w-full bg-ink/10"><div class="h-px bg-gold transition-[width] duration-700" :style="`width:${Math.min(100, ($store.cart.progress || 0) * 100)}%`"></div></div>
                </div>

                <ul class="divide-y divide-ink/10">
                    <template x-for="item in $store.cart.items" :key="item.key">
                        <li class="flex gap-6 py-6">
                            <a :href="item.product.url" class="relative aspect-[3/4] w-28 shrink-0 overflow-hidden bg-sand sm:w-36"><img :src="item.product.images[0]" :alt="item.product.name" class="img-cover"></a>
                            <div class="flex min-w-0 flex-1 flex-col">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="text-[0.5625rem] uppercase tracking-[0.2em] text-taupe" x-text="item.product.category"></p>
                                        <a :href="item.product.url" class="mt-1 block font-serif text-xl leading-tight" x-text="item.product.name"></a>
                                        <p class="mt-1 text-[0.75rem] text-smoke" x-text="(item.color ? item.color + ' · ' : '') + 'Size ' + item.size"></p>
                                        <p class="mt-1 text-[0.75rem] tabular-nums text-smoke" x-text="item.product.price_formatted + ' each'"></p>
                                    </div>
                                    <p class="shrink-0 tabular-nums" x-text="item.line_total_formatted"></p>
                                </div>
                                <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-4">
                                    <div class="flex h-9 items-center border border-ink/15">
                                        <button type="button" @click="$store.cart.update(item.key, item.qty - 1)" aria-label="Decrease" class="grid h-full w-9 place-items-center hover:bg-sand"><x-ico name="minus" :size="12" :stroke="1.5" /></button>
                                        <span class="w-8 text-center text-xs tabular-nums" x-text="item.qty"></span>
                                        <button type="button" @click="$store.cart.update(item.key, item.qty + 1)" aria-label="Increase" class="grid h-full w-9 place-items-center hover:bg-sand"><x-ico name="plus" :size="12" :stroke="1.5" /></button>
                                    </div>
                                    <div class="flex items-center gap-4 text-[0.625rem] uppercase tracking-widest text-smoke">
                                        <button type="button" @click="$store.wishlist.toggle(item.product.id); $store.cart.remove(item.key)" class="hover:text-ink">Move to wishlist</button>
                                        <button type="button" @click="$store.cart.remove(item.key)" class="hover:text-ink">Remove</button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </template>
                </ul>

                {{-- Promo code --}}
                <div class="border-t border-ink/10 pt-6">
                    <p class="eyebrow mb-3 text-taupe">Promo code</p>
                    <template x-if="$store.cart.coupon">
                        <div class="flex items-center justify-between border border-ink/15 bg-cream px-4 py-3 text-sm">
                            <span><span class="font-medium" x-text="$store.cart.coupon.code"></span> <span class="text-smoke">— <span x-text="$store.cart.coupon.label"></span></span></span>
                            <button type="button" @click="$store.cart.removeCoupon()" class="text-[0.625rem] uppercase tracking-widest text-smoke hover:text-ink">Remove</button>
                        </div>
                    </template>
                    <template x-if="!$store.cart.coupon">
                        <form @submit.prevent="$store.cart.applyCoupon(code)" class="flex max-w-md items-end gap-4">
                            <input x-model="code" placeholder="Enter code" class="input-luxe uppercase" aria-label="Promo code">
                            <button type="submit" :disabled="$store.cart.couponBusy" class="shrink-0 border-b border-ink pb-3 text-[0.6875rem] uppercase tracking-[0.2em] hover:opacity-60">Apply</button>
                        </form>
                    </template>
                    <p x-show="$store.cart.couponError" x-text="$store.cart.couponError" class="mt-2 text-sm text-rouge"></p>
                </div>
            </div>

            <aside class="lg:col-span-4">
                <div class="border border-ink/10 bg-cream p-6 lg:sticky lg:top-28">
                    <h2 class="font-serif text-2xl">Order summary</h2>
                    <dl class="mt-6 space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-smoke">Subtotal</dt><dd class="tabular-nums" x-text="$store.cart.subtotal_formatted"></dd></div>
                        <div class="flex justify-between" x-show="$store.cart.discount > 0"><dt class="text-smoke">Discount</dt><dd class="tabular-nums text-emerald-700" x-text="'− ' + $store.cart.discount_formatted"></dd></div>
                        <div class="flex justify-between"><dt class="text-smoke">Shipping <span class="text-[0.625rem]" x-text="'(' + $store.cart.shipping_method.name + ')'"></span></dt><dd class="tabular-nums" x-text="$store.cart.shipping_formatted"></dd></div>
                        <div class="flex justify-between"><dt class="text-smoke" x-text="'Taxes (' + $store.cart.tax_label + ')'"></dt><dd class="tabular-nums" x-text="$store.cart.tax_formatted"></dd></div>
                        <div class="flex justify-between border-t border-ink/10 pt-3 text-base"><dt>Total</dt><dd class="tabular-nums" x-text="$store.cart.total_formatted"></dd></div>
                    </dl>
                    <a href="{{ route('checkout') }}" class="btn btn-primary btn-lg mt-6 w-full"><x-ico name="lock" :size="12" :stroke="1.5" /> Proceed to Checkout</a>
                    <p class="mt-4 text-center text-[0.6875rem] text-taupe">Secure checkout · Cards, UPI, wallets & COD</p>
                    <div class="mt-4 flex flex-wrap justify-center gap-1.5">
                        @foreach(array_slice(setting('site.payment_methods', []), 0, 6) as $m)<span class="border border-ink/15 px-1.5 py-0.5 text-[0.5rem] font-semibold uppercase tracking-wider text-smoke">{{ $m }}</span>@endforeach
                    </div>
                </div>
            </aside>
        </div>

        {{-- Recommendations --}}
        <div x-show="$store.cart.recommendations.length" x-cloak class="mt-20 border-t border-ink/10 pt-16">
            <x-section-header eyebrow="Before you go" title="You may also like" />
            <div class="mt-10 grid grid-cols-2 gap-x-4 gap-y-10 lg:grid-cols-4 lg:gap-x-6">
                <template x-for="p in $store.cart.recommendations" :key="p.id">
                    <div class="group">
                        <a :href="p.url" class="relative block aspect-[3/4] overflow-hidden bg-sand"><img :src="p.images[0]" :alt="p.name" class="img-cover img-zoom"></a>
                        <p class="mt-4 text-[0.5625rem] uppercase tracking-[0.2em] text-taupe" x-text="p.category"></p>
                        <p class="mt-1 font-serif text-[1.0625rem] leading-snug" x-text="p.name"></p>
                        <div class="mt-2 flex items-center justify-between"><span class="text-sm tabular-nums" x-text="p.price_formatted"></span><button type="button" @click="$store.ui.showQuickView(p.slug)" class="text-[0.625rem] uppercase tracking-[0.2em] underline-offset-4 hover:underline">Quick add</button></div>
                    </div>
                </template>
            </div>
        </div>
    </section>
@endsection
