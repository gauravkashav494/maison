{{--
    A phone number field: exactly 10 digits, the same rule as the server (StorefrontRequest::phone,
    mirrored by phoneError() in order-tools.js). The shared phone-input script keeps it to digits
    and turns a pasted +91 or leading 0 number into its 10 digits. A message from the server shows until
    the buyer starts editing; an invalid number stops the form being sent.
--}}
@props(['name' => 'phone', 'value' => null, 'required' => false, 'label' => 'Phone'])

@php $id = 'bk-'.$name.'-'.\Illuminate\Support\Str::random(4); @endphp

<label {{ $attributes->merge(['class' => 'bk-label']) }} x-data="phoneField(@js((bool) $required), @js($errors->first($name)))">
    {{ $label }}@if($required)<span class="bk-req">*</span>@else <span class="bk-muted font-normal">(optional)</span>@endif
    <input x-ref="input" name="{{ $name }}" type="tel" inputmode="numeric" autocomplete="tel"
           placeholder="10-digit number" class="bk-input" value="{{ old($name, $value) }}"
           :class="error && '!border-[var(--bk-out)]'" :aria-invalid="error ? 'true' : 'false'" aria-describedby="{{ $id }}"
           @input="clean()" @blur="touched = true; check()">
    <span id="{{ $id }}" class="bk-error" x-show="error" x-text="error" role="alert">{{ $errors->first($name) }}</span>
</label>
