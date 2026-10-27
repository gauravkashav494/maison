@extends('layouts.app')

{{--
    The order page: the same shared cart store, coupon and shipping behaviour as every other
    template, laid out as a table with no thumbnails.
--}}

@section('content')
<div class="ol-page" x-data="{ code: '' }">
    <div class="ol-head">
        <h1>Your order</h1>
        <p><a href="{{ route('shop.index') }}">Back to the order list</a></p>
    </div>

    <p class="ol-empty" x-show="$store.cart.loaded && !$store.cart.items.length">
        Nothing in the order yet. <a href="{{ route('shop.index') }}">Add products from the list.</a>
    </p>

    <div x-show="$store.cart.items.length" x-cloak>
        <table class="ol-table">
            <thead>
                <tr>
                    <th scope="col">Product</th>
                    <th scope="col">Code</th>
                    <th scope="col" class="ol-price">Price</th>
                    <th scope="col">Quantity</th>
                    <th scope="col" class="ol-price">Line total</th>
                    <th scope="col"><span class="sr-only">Remove</span></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="item in $store.cart.items" :key="item.key">
                    <tr>
                        <td class="ol-name">
                            <a :href="item.product.url" x-text="item.product.name"></a>
                            <span class="ol-sub" x-show="item.options_label" x-text="item.options_label"></span>
                        </td>
                        <td class="ol-code" data-label="Code" x-text="item.variant_sku || '—'"></td>
                        <td class="ol-price" data-label="Price" x-text="item.product.price_formatted"></td>
                        <td data-label="Quantity">
                            <span class="ol-qty">
                                <button type="button" @click="$store.cart.update(item.key, item.qty - 1)" aria-label="Decrease">−</button>
                                <input type="number" min="0" :value="item.qty" @change="$store.cart.update(item.key, parseInt($event.target.value, 10) || 0)" aria-label="Quantity">
                                <button type="button" @click="$store.cart.update(item.key, item.qty + 1)" aria-label="Increase">+</button>
                            </span>
                        </td>
                        <td class="ol-price" data-label="Line total" x-text="item.line_total_formatted"></td>
                        <td data-label=""><button type="button" class="ol-choose" @click="$store.cart.remove(item.key)">Remove</button></td>
                    </tr>
                </template>
            </tbody>
        </table>

        <div style="display:grid;gap:1.25rem;margin-top:1.25rem" class="md:grid-cols-2">
            <div>
                <p class="ol-sub" style="margin-bottom:.4rem">Promo code</p>
                <template x-if="$store.cart.coupon">
                    <p style="display:flex;gap:.75rem;align-items:center">
                        <strong x-text="$store.cart.coupon.code"></strong>
                        <span class="ol-sub" style="display:inline" x-text="$store.cart.coupon.label"></span>
                        <button type="button" class="ol-choose" @click="$store.cart.removeCoupon()">Remove</button>
                    </p>
                </template>
                <template x-if="!$store.cart.coupon">
                    <form @submit.prevent="$store.cart.applyCoupon(code)" style="display:flex;gap:.5rem;max-width:22rem">
                        <span class="ol-search" style="flex:1 1 auto"><input x-model="code" placeholder="Enter code" aria-label="Promo code"></span>
                        <button type="submit" class="ol-add is-secondary" :disabled="$store.cart.couponBusy">Apply</button>
                    </form>
                </template>
                <p class="ol-note" x-show="$store.cart.couponError" x-text="$store.cart.couponError"></p>

                <p class="ol-sub" style="margin:1rem 0 .4rem">Delivery</p>
                <select class="ol-select" @change="$store.cart.setShipping($event.target.value)" aria-label="Delivery method">
                    @foreach(app(\App\Services\Cart::class)->shippingMethods() as $method)
                        <option value="{{ $method['code'] }}">{{ $method['name'] }} — {{ $method['cost'] === 0 ? 'free' : money($method['cost']) }}</option>
                    @endforeach
                </select>
            </div>

            <dl class="ol-detail" style="gap:.4rem 1.25rem">
                <dt>Subtotal</dt><dd x-text="$store.cart.subtotal_formatted"></dd>
                <template x-if="$store.cart.discount > 0">
                    <dt>Discount</dt>
                </template>
                <template x-if="$store.cart.discount > 0">
                    <dd x-text="'− ' + $store.cart.discount_formatted"></dd>
                </template>
                <dt>Delivery</dt><dd x-text="$store.cart.shipping_formatted"></dd>
                <dt x-text="'Tax (' + $store.cart.tax_label + ')'"></dt><dd x-text="$store.cart.tax_formatted"></dd>
                <dt><strong>Total</strong></dt><dd><strong x-text="$store.cart.total_formatted"></strong></dd>
            </dl>
        </div>

        <p style="margin-top:1.25rem">
            <a href="{{ route('checkout') }}" class="ol-add" style="text-decoration:none">Go to checkout</a>
        </p>
    </div>
</div>
@endsection
