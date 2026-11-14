{{--
    A text field checked in the browser with the server's own rules (StorefrontRequest, mirrored
    by fieldError() in order-tools.js): a missing or invalid value shows its message under the
    field and stops the form being sent, together with every other checked field on the form.
    A message from the server shows until the buyer starts editing.

    rule: email | name | line | place | postal. attribute: the words used in the message,
    e.g. "email address" for "Please enter your email address.".
--}}
@props([
    'name', 'label', 'rule', 'attribute' => null, 'value' => null, 'required' => false,
    'type' => 'text', 'autocomplete' => null, 'placeholder' => null, 'inputmode' => null,
])

@php $id = 'bk-'.$name.'-'.\Illuminate\Support\Str::random(4); @endphp

<label {{ $attributes->merge(['class' => 'bk-label']) }} x-data="checkField(@js($rule), @js($attribute ?? \Illuminate\Support\Str::lower($label)), @js((bool) $required), @js($errors->first($name)))">
    {{ $label }}@if($required)<span class="bk-req">*</span>@else <span class="bk-muted font-normal">(optional)</span>@endif
    <input x-ref="input" name="{{ $name }}" type="{{ $type }}" class="bk-input {{ $errors->has($name) ? 'border-[var(--bk-out)]' : '' }}" value="{{ old($name, $value) }}"
           @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           @if($placeholder) placeholder="{{ $placeholder }}" @endif
           @if($inputmode) inputmode="{{ $inputmode }}" @endif
           :class="error && '!border-[var(--bk-out)]'" :aria-invalid="error ? 'true' : 'false'" aria-describedby="{{ $id }}"
           @input="clean()" @blur="touched = true; check()">
    <span id="{{ $id }}" class="bk-error" x-show="error" x-text="error" role="alert">{{ $errors->first($name) }}</span>
</label>
