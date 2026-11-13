@php $label = 'block text-[0.6875rem] uppercase tracking-[0.2em] text-smoke'; $v = fn ($k) => old($k, $a?->{$k}); @endphp
<div class="grid gap-4 sm:grid-cols-2">
    <label><span class="{{ $label }}">Label</span><input name="label" value="{{ $v('label') }}" placeholder="Home, Office…" class="input-luxe">@error('label')<span class="block text-xs text-rouge">{{ $message }}</span>@enderror</label>
    <label><span class="{{ $label }}">Full name<span class="req" aria-hidden="true">*</span></span><input name="name" value="{{ $v('name') ?? auth()->user()->name }}" class="input-luxe">@error('name')<span class="block text-xs text-rouge">{{ $message }}</span>@enderror</label>
    <label class="sm:col-span-2"><span class="{{ $label }}">Address<span class="req" aria-hidden="true">*</span></span><input name="line1" value="{{ $v('line1') }}" class="input-luxe">@error('line1')<span class="block text-xs text-rouge">{{ $message }}</span>@enderror</label>
    <label class="sm:col-span-2"><span class="{{ $label }}">Apartment, landmark</span><input name="line2" value="{{ $v('line2') }}" class="input-luxe">@error('line2')<span class="block text-xs text-rouge">{{ $message }}</span>@enderror</label>
    <label><span class="{{ $label }}">City<span class="req" aria-hidden="true">*</span></span><input name="city" value="{{ $v('city') }}" class="input-luxe">@error('city')<span class="block text-xs text-rouge">{{ $message }}</span>@enderror</label>
    <label><span class="{{ $label }}">State<span class="req" aria-hidden="true">*</span></span><input name="state" value="{{ $v('state') }}" class="input-luxe">@error('state')<span class="block text-xs text-rouge">{{ $message }}</span>@enderror</label>
    <label><span class="{{ $label }}">Postal code<span class="req" aria-hidden="true">*</span></span><input name="postal_code" value="{{ $v('postal_code') }}" class="input-luxe">@error('postal_code')<span class="block text-xs text-rouge">{{ $message }}</span>@enderror</label>
    <label><span class="{{ $label }}">Country<span class="req" aria-hidden="true">*</span></span><input name="country" value="{{ $v('country') ?? 'India' }}" class="input-luxe">@error('country')<span class="block text-xs text-rouge">{{ $message }}</span>@enderror</label>
    <label><span class="{{ $label }}">Phone</span><input name="phone" value="{{ $v('phone') }}" class="input-luxe">@error('phone')<span class="block text-xs text-rouge">{{ $message }}</span>@enderror</label>
    <label class="flex items-center gap-2 self-end pb-3 text-sm"><input type="checkbox" name="is_default" value="1" class="accent-ink" @checked($a?->is_default)> Set as default</label>
</div>
@if($errors->any())<p class="text-xs text-rouge">{{ $errors->first() }}</p>@endif
