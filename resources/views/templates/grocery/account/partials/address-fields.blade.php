@php $v = fn ($k) => old($k, $a?->{$k}); @endphp
<div class="grid gap-3 sm:grid-cols-2">
    <label><span class="label">Label</span><input name="label" value="{{ $v('label') }}" placeholder="Home, Office…" class="field">@error('label')<span class="error-text">{{ $message }}</span>@enderror</label>
    <label><span class="label">Full name<span class="req" aria-hidden="true">*</span></span><input name="name" value="{{ $v('name') }}" class="field">@error('name')<span class="error-text">{{ $message }}</span>@enderror</label>
    <label class="sm:col-span-2"><span class="label">House / flat, building, street<span class="req" aria-hidden="true">*</span></span><input name="line1" value="{{ $v('line1') }}" class="field">@error('line1')<span class="error-text">{{ $message }}</span>@enderror</label>
    <label class="sm:col-span-2"><span class="label">Landmark, area</span><input name="line2" value="{{ $v('line2') }}" class="field">@error('line2')<span class="error-text">{{ $message }}</span>@enderror</label>
    <label><span class="label">City<span class="req" aria-hidden="true">*</span></span><input name="city" value="{{ $v('city') }}" class="field">@error('city')<span class="error-text">{{ $message }}</span>@enderror</label>
    <label><span class="label">State<span class="req" aria-hidden="true">*</span></span><input name="state" value="{{ $v('state') }}" class="field">@error('state')<span class="error-text">{{ $message }}</span>@enderror</label>
    <label><span class="label">Pincode<span class="req" aria-hidden="true">*</span></span><input name="postal_code" value="{{ $v('postal_code') }}" class="field" inputmode="numeric">@error('postal_code')<span class="error-text">{{ $message }}</span>@enderror</label>
    <label><span class="label">Country<span class="req" aria-hidden="true">*</span></span><input name="country" value="{{ $v('country') ?? 'India' }}" class="field">@error('country')<span class="error-text">{{ $message }}</span>@enderror</label>
    <label><span class="label">Phone</span><input name="phone" value="{{ $v('phone') }}" class="field" inputmode="tel">@error('phone')<span class="error-text">{{ $message }}</span>@enderror</label>
    <label class="flex items-center gap-2 self-end pb-3 text-sm"><input type="checkbox" name="is_default" value="1" class="check" @checked(old('is_default', $a?->is_default))> Set as default</label>
</div>
@if($errors->any())<p class="error-text">{{ $errors->first() }}</p>@endif
