{{--
    A sales person's checkout, in place of the contact & address fields (SalesOrder): who the
    order is for, and for a customer their name and phone. No address is asked or put on the
    order. The seller's state and country are sent hidden only so the checkout's `checkout`
    component names the tax as the order will (CGST + SGST within the seller's state); the
    server never reads them from here.

    Styles come from the template: $labelClass, $fieldClass, $errorClass, $radioClass.
--}}
<div x-data="{
    orderFor: @js(old('order_for', \App\Support\SalesOrder::FOR_SELF)),
    customerName: @js(old('shipping_name', '')),
    customerEmail: @js(old('email', '')),
    autoFilledName: false,
    autoFilledEmail: false,
    lookupVersion: 0,
    async lookupCustomer(value) {
        const lookup = ++this.lookupVersion
        const digits = (value ?? '').replace(/\D+/g, '')
        const phone = digits.length === 12 && digits.startsWith('91') ? digits.slice(2) : (digits.length === 11 && digits.startsWith('0') ? digits.slice(1) : digits)
        if (this.autoFilledName) { this.customerName = ''; this.autoFilledName = false }
        if (this.autoFilledEmail) { this.customerEmail = ''; this.autoFilledEmail = false }
        if (!/^\d{10}$/.test(phone)) return
        try {
            const response = await fetch(@js(route('checkout.sales-customer')), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                body: JSON.stringify({ phone }),
            })
            if (!response.ok || lookup !== this.lookupVersion) return
            const customer = await response.json()
            if (customer.name && !this.customerName) {
                this.customerName = customer.name
                this.autoFilledName = true
            }
            if (customer.email && !this.customerEmail) {
                this.customerEmail = customer.email
                this.autoFilledEmail = true
            }
        } catch (_) {}
    }
}" class="mt-3">
    <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm" role="radiogroup" aria-label="Order for">
        <label class="flex cursor-pointer items-center gap-2">
            <input type="radio" name="order_for" value="{{ \App\Support\SalesOrder::FOR_SELF }}" x-model="orderFor" class="{{ $radioClass }}">
            <span>Myself <span class="opacity-70">({{ $user->name }})</span></span>
        </label>
        <label class="flex cursor-pointer items-center gap-2">
            <input type="radio" name="order_for" value="{{ \App\Support\SalesOrder::FOR_CUSTOMER }}" x-model="orderFor" class="{{ $radioClass }}">
            <span>A customer</span>
        </label>
    </div>
    @error('order_for')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror

    {{-- Disabled (so not sent) while the order is for the sales person. --}}
    <div x-show="orderFor === @js(\App\Support\SalesOrder::FOR_CUSTOMER)" x-cloak class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2.5 sm:grid-cols-12">
        <label class="col-span-2 sm:col-span-4"><span class="{{ $labelClass }}">Customer phone<span class="req" aria-hidden="true">*</span></span><input x-ref="customerPhone" x-on:input.debounce.500ms="lookupCustomer($event.target.value)" name="phone" type="tel" value="{{ old('phone') }}" class="{{ $fieldClass }}" autocomplete="off" inputmode="tel" :disabled="orderFor !== @js(\App\Support\SalesOrder::FOR_CUSTOMER)">@error('phone')<span class="{{ $errorClass }}">{{ $message }}</span>@enderror</label>
        <label class="col-span-2 sm:col-span-4"><span class="{{ $labelClass }}">Customer name<span class="req" aria-hidden="true">*</span></span><input x-model="customerName" x-on:input="autoFilledName = false" name="shipping_name" class="{{ $fieldClass }}" autocomplete="off" :disabled="orderFor !== @js(\App\Support\SalesOrder::FOR_CUSTOMER)">@error('shipping_name')<span class="{{ $errorClass }}">{{ $message }}</span>@enderror</label>
        <label class="col-span-2 sm:col-span-4"><span class="{{ $labelClass }}">Customer email (optional)</span><input x-model="customerEmail" x-on:input="autoFilledEmail = false" name="email" type="email" class="{{ $fieldClass }}" autocomplete="off" :disabled="orderFor !== @js(\App\Support\SalesOrder::FOR_CUSTOMER)">@error('email')<span class="{{ $errorClass }}">{{ $message }}</span>@enderror</label>
        <p class="col-span-2 w-full text-xs opacity-70 sm:col-span-4">An existing customer with this phone number gets the order; otherwise a new customer is added.</p>
    </div>

    <p class="mt-3 text-xs opacity-70">No delivery — this order is handed over at the store.</p>
    <input type="hidden" name="shipping_state" value="{{ \App\Support\SalesOrder::supplyState() }}">
    <input type="hidden" name="shipping_country" value="India">
</div>
