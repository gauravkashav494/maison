@php $v = fn ($k) => old($k, $a?->{$k}); @endphp
<div class="grid gap-3 sm:grid-cols-2">
    <label><span class="label">Label</span><input name="label" value="{{ $v('label') }}" placeholder="Home, Shop, Office…" class="field"></label>
    <label><span class="label">Contact name</span><input name="name" required value="{{ $v('name') }}" class="field"></label>
    <label class="sm:col-span-2"><span class="label">House / flat, building, street</span><input name="line1" required value="{{ $v('line1') }}" class="field"></label>
    <label class="sm:col-span-2"><span class="label">Landmark, locality</span><input name="line2" value="{{ $v('line2') }}" class="field"></label>
    <label><span class="label">City</span><input name="city" required value="{{ $v('city') }}" class="field"></label>
    <label><span class="label">State</span><input name="state" required value="{{ $v('state') }}" class="field"></label>
    <label><span class="label">Pincode</span><input name="postal_code" required value="{{ $v('postal_code') }}" class="field" inputmode="numeric"></label>
    <label><span class="label">Country</span><input name="country" required value="{{ $v('country') ?? 'India' }}" class="field"></label>
    <label><span class="label">Phone at this address</span><input name="phone" value="{{ $v('phone') }}" class="field" inputmode="tel"></label>
    <label class="flex items-center gap-2 self-end pb-3 text-sm"><input type="checkbox" name="is_default" value="1" class="check" @checked(old('is_default', $a?->is_default))> Set as default</label>
</div>
@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif