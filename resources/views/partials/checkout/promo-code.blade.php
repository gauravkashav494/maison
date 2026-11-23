@module('coupons')
<div x-data="{ code: '' }" class="{{ $containerClass }}">
    <p class="{{ $labelClass }}">Have a promo code?</p>
    <div x-show="!$store.cart.coupon" class="mt-2 flex gap-2">
        <input x-model="code" x-on:keydown.enter.prevent="$store.cart.applyCoupon(code)" type="text" autocomplete="off" placeholder="Enter code" aria-label="Promo code" class="{{ $inputClass }}">
        <button type="button" x-on:click="$store.cart.applyCoupon(code)" :disabled="$store.cart.couponBusy" class="{{ $buttonClass }}">
            <span x-text="$store.cart.couponBusy ? 'Applying…' : 'Apply'">Apply</span>
        </button>
    </div>
    <div x-cloak x-show="$store.cart.coupon" class="{{ $couponClass }}">
        <span><strong x-text="$store.cart.coupon?.code"></strong> applied <span x-text="$store.cart.coupon?.label ? '· ' + $store.cart.coupon.label : ''"></span></span>
        <button type="button" x-on:click="$store.cart.removeCoupon()" class="{{ $removeClass }}">Remove</button>
    </div>
    <p x-cloak x-show="$store.cart.couponError" x-text="$store.cart.couponError" class="{{ $errorClass }}"></p>
</div>
@endmodule
