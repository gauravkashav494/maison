{{--
    A sales person's checkout (SalesOrder): who the order is for, shown above the usual contact
    and delivery address fields of the template. Those fields stay the template's own; this only
    fills them in:

    - "Myself": the sales person's own details and saved address, as for any signed-in shopper.
    - "A customer": the fields are cleared for the customer's details, and a known customer's
      name, email and saved address are filled in from their phone number. Switching back and
      forth keeps what was typed for each. "Save this address" is for the sales person's own
      account only, so it is hidden.

    A line of the script below must not end with @js(...): Blade drops the line break after it,
    which joins the next line onto it and breaks the whole script. End such a line with ";".

    Styles come from the template: $errorClass, $radioClass.
--}}
@php
    $selfDetails = [
        'phone' => $user?->phone ?? $address?->phone,
        'shipping_name' => $address?->name ?? $user?->name,
        'email' => $user?->email,
        'shipping_line1' => $address?->line1,
        'shipping_line2' => $address?->line2,
        'shipping_city' => $address?->city,
        'shipping_state' => $address?->state,
        'shipping_postal_code' => $address?->postal_code,
    ];
@endphp
<div x-data="{
    orderFor: @js(old('order_for', \App\Support\SalesOrder::FOR_SELF)),
    fields: @js(array_keys($selfDetails)),
    kept: { self: @js(array_map(fn ($v) => (string) $v, $selfDetails)), customer: {} },
    autoFilled: {},
    filling: false,
    lookupVersion: 0,
    init() {
        this.form = this.$el.closest('form')
        this.form.addEventListener('input', (e) => {
            if (this.filling || !this.fields.includes(e.target.name)) return
            delete this.autoFilled[e.target.name]
            if (e.target.name === 'phone' && this.orderFor === @js(\App\Support\SalesOrder::FOR_CUSTOMER)) this.lookupLater(e.target.value)
        })
        this.toggleSaveAddress()
    },
    input(name) { return this.form.querySelector(`[name='${name}']`) },
    set(name, value) {
        const el = this.input(name)
        if (!el) return
        this.filling = true
        el.value = value ?? ''
        el.dispatchEvent(new Event('input', { bubbles: true }))
        el.dispatchEvent(new Event('change', { bubbles: true }))
        this.filling = false
    },
    switchTo(to) {
        const from = to === @js(\App\Support\SalesOrder::FOR_SELF) ? @js(\App\Support\SalesOrder::FOR_CUSTOMER) : @js(\App\Support\SalesOrder::FOR_SELF);
        this.kept[from] = Object.fromEntries(this.fields.map((name) => [name, this.input(name)?.value ?? '']))
        this.fields.forEach((name) => this.set(name, this.kept[to][name] ?? ''))
        this.autoFilled = {}
        this.toggleSaveAddress()
    },
    toggleSaveAddress() {
        const box = this.input('save_address')
        if (!box) return
        const self = this.orderFor === @js(\App\Support\SalesOrder::FOR_SELF);
        box.disabled = !self
        box.closest('label').style.display = self ? '' : 'none'
    },
    lookupLater(value) {
        clearTimeout(this.lookupTimer)
        this.lookupTimer = setTimeout(() => this.lookupCustomer(value), 500)
    },
    async lookupCustomer(value) {
        const lookup = ++this.lookupVersion
        const digits = (value ?? '').replace(/\D+/g, '')
        const phone = digits.length === 12 && digits.startsWith('91') ? digits.slice(2) : (digits.length === 11 && digits.startsWith('0') ? digits.slice(1) : digits)
        // A new number: what was filled in for the last one goes, what was typed stays.
        Object.keys(this.autoFilled).forEach((name) => this.set(name, ''))
        this.autoFilled = {}
        if (!/^[6-9]\d{9}$/.test(phone)) return
        try {
            const response = await fetch(@js(route('checkout.sales-customer')), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                body: JSON.stringify({ phone }),
            })
            if (!response.ok || lookup !== this.lookupVersion || this.orderFor !== @js(\App\Support\SalesOrder::FOR_CUSTOMER)) return
            const customer = await response.json()
            const fill = { shipping_name: customer.name, email: customer.email }
            // A saved address only goes in when no part of an address has been typed yet.
            const addressFields = Object.keys(customer.address ?? {})
            if (addressFields.length && addressFields.every((name) => !this.input(name)?.value)) Object.assign(fill, customer.address)
            Object.entries(fill).forEach(([name, value]) => {
                if (!value || this.input(name)?.value) return
                this.set(name, value)
                this.autoFilled[name] = true
            })
        } catch (_) {}
    }
}" class="mt-3">
    <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm" role="radiogroup" aria-label="Order for">
        <label class="flex cursor-pointer items-center gap-2">
            <input type="radio" name="order_for" value="{{ \App\Support\SalesOrder::FOR_SELF }}" x-model="orderFor" @change="switchTo(orderFor)" class="{{ $radioClass }}">
            <span>Myself <span class="opacity-70">({{ $user->name }})</span></span>
        </label>
        <label class="flex cursor-pointer items-center gap-2">
            <input type="radio" name="order_for" value="{{ \App\Support\SalesOrder::FOR_CUSTOMER }}" x-model="orderFor" @change="switchTo(orderFor)" class="{{ $radioClass }}">
            <span>A customer</span>
        </label>
    </div>
    @error('order_for')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
    <p x-show="orderFor === @js(\App\Support\SalesOrder::FOR_CUSTOMER)" x-cloak class="mt-2 text-xs opacity-70">Enter the customer's phone number first: a known customer's name and saved address are filled in. An existing customer with this phone number gets the order; otherwise a new customer is added.</p>
</div>
