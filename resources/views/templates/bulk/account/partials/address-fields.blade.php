{{-- The address form's fields; names match what the shared account controller validates. --}}
@php $v = fn ($k) => old($k, $a?->{$k}); @endphp
<div class="grid gap-4 sm:grid-cols-2">
    <label class="bk-label">Label<input name="label" value="{{ $v('label') }}" placeholder="Kitchen, Head office…" class="bk-input">@error('label')<span class="bk-error">{{ $message }}</span>@enderror</label>
    <label class="bk-label">Contact name<span class="bk-req">*</span><input name="name" value="{{ $v('name') ?? auth()->user()->name }}" class="bk-input" autocomplete="name">@error('name')<span class="bk-error">{{ $message }}</span>@enderror</label>
    <label class="bk-label sm:col-span-2">Address<span class="bk-req">*</span><input name="line1" value="{{ $v('line1') }}" class="bk-input" autocomplete="address-line1">@error('line1')<span class="bk-error">{{ $message }}</span>@enderror</label>
    <label class="bk-label sm:col-span-2">Building, landmark<input name="line2" value="{{ $v('line2') }}" class="bk-input" autocomplete="address-line2">@error('line2')<span class="bk-error">{{ $message }}</span>@enderror</label>
    <label class="bk-label">City<span class="bk-req">*</span><input name="city" value="{{ $v('city') }}" class="bk-input" autocomplete="address-level2">@error('city')<span class="bk-error">{{ $message }}</span>@enderror</label>
    <label class="bk-label">State<span class="bk-req">*</span><input name="state" value="{{ $v('state') }}" class="bk-input" autocomplete="address-level1">@error('state')<span class="bk-error">{{ $message }}</span>@enderror</label>
    <label class="bk-label">PIN code<span class="bk-req">*</span><input name="postal_code" value="{{ $v('postal_code') }}" class="bk-input" inputmode="numeric" autocomplete="postal-code">@error('postal_code')<span class="bk-error">{{ $message }}</span>@enderror</label>
    <label class="bk-label">Country<span class="bk-req">*</span><input name="country" value="{{ $v('country') ?? 'India' }}" class="bk-input" autocomplete="country-name">@error('country')<span class="bk-error">{{ $message }}</span>@enderror</label>
    <label class="bk-label">Phone<input name="phone" type="tel" value="{{ $v('phone') }}" class="bk-input" autocomplete="tel">@error('phone')<span class="bk-error">{{ $message }}</span>@enderror</label>
    <label class="flex items-center gap-2 self-end pb-2.5 text-sm font-semibold"><input type="checkbox" name="is_default" value="1" class="size-4 accent-[var(--bk-brand)]" @checked($a?->is_default)> Use as default address</label>
</div>
