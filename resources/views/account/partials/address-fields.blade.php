@php $label = 'block text-[0.6875rem] uppercase tracking-[0.2em] text-smoke'; $v = fn ($k) => old($k, $a?->{$k}); @endphp
<div class="grid gap-4 sm:grid-cols-2">
    <label><span class="{{ $label }}">Label</span><input name="label" value="{{ $v('label') }}" placeholder="Home, Office…" class="input-luxe"></label>
    <label><span class="{{ $label }}">Full name</span><input name="name" required value="{{ $v('name') ?? auth()->user()->name }}" class="input-luxe"></label>
    <label class="sm:col-span-2"><span class="{{ $label }}">Address</span><input name="line1" required value="{{ $v('line1') }}" class="input-luxe"></label>
    <label class="sm:col-span-2"><span class="{{ $label }}">Apartment, landmark</span><input name="line2" value="{{ $v('line2') }}" class="input-luxe"></label>
    <label><span class="{{ $label }}">City</span><input name="city" required value="{{ $v('city') }}" class="input-luxe"></label>
    <label><span class="{{ $label }}">State</span><input name="state" required value="{{ $v('state') }}" class="input-luxe"></label>
    <label><span class="{{ $label }}">Postal code</span><input name="postal_code" required value="{{ $v('postal_code') }}" class="input-luxe"></label>
    <label><span class="{{ $label }}">Country</span><input name="country" required value="{{ $v('country') ?? 'India' }}" class="input-luxe"></label>
    <label><span class="{{ $label }}">Phone</span><input name="phone" value="{{ $v('phone') }}" class="input-luxe"></label>
    <label class="flex items-center gap-2 self-end pb-3 text-sm"><input type="checkbox" name="is_default" value="1" class="accent-ink" @checked($a?->is_default)> Set as default</label>
</div>
@if($errors->any())<p class="text-xs text-rouge">{{ $errors->first() }}</p>@endif
