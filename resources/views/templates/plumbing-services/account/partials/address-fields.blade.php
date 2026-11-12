@php $v = fn ($k) => old($k, $a?->{$k}); @endphp
<div class="grid gap-3 sm:grid-cols-2">
    <label><span class="label">Label</span><input name="label" value="{{ $v('label') }}" placeholder="Home, Shop, Office…" class="field">@error('label')<p class="error">{{ $message }}</p>@enderror</label>
    <label><span class="label">Contact name<span class="req" aria-hidden="true">*</span></span><input name="name" value="{{ $v('name') }}" class="field">@error('name')<p class="error">{{ $message }}</p>@enderror</label>
    <label class="sm:col-span-2"><span class="label">House / flat, building, street<span class="req" aria-hidden="true">*</span></span><input name="line1" value="{{ $v('line1') }}" class="field">@error('line1')<p class="error">{{ $message }}</p>@enderror</label>
    <label class="sm:col-span-2"><span class="label">Landmark, locality</span><input name="line2" value="{{ $v('line2') }}" class="field">@error('line2')<p class="error">{{ $message }}</p>@enderror</label>
    <label><span class="label">City<span class="req" aria-hidden="true">*</span></span><input name="city" value="{{ $v('city') }}" class="field">@error('city')<p class="error">{{ $message }}</p>@enderror</label>
    <label><span class="label">State<span class="req" aria-hidden="true">*</span></span><input name="state" value="{{ $v('state') }}" class="field">@error('state')<p class="error">{{ $message }}</p>@enderror</label>
    <label><span class="label">Pincode<span class="req" aria-hidden="true">*</span></span><input name="postal_code" value="{{ $v('postal_code') }}" class="field" inputmode="numeric">@error('postal_code')<p class="error">{{ $message }}</p>@enderror</label>
    <label><span class="label">Country<span class="req" aria-hidden="true">*</span></span><input name="country" value="{{ $v('country') ?? 'India' }}" class="field">@error('country')<p class="error">{{ $message }}</p>@enderror</label>
    <label><span class="label">Phone at this address</span><input name="phone" value="{{ $v('phone') }}" class="field" inputmode="tel">@error('phone')<p class="error">{{ $message }}</p>@enderror</label>
    <label class="flex items-center gap-2 self-end pb-3 text-sm"><input type="checkbox" name="is_default" value="1" class="check" @checked(old('is_default', $a?->is_default))> Set as default</label>
</div>
@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif