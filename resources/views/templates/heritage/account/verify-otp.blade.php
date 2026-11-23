@extends('layouts.app')

@section('content')
<x-auth-shell :title="$heading" :text="$intro">
    <form method="post" action="{{ route('otp.verify') }}" class="space-y-4" novalidate>
        @csrf
        <label class="block"><span class="label">WhatsApp code<span class="req" aria-hidden="true">*</span></span><input name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ $codeLength }}" autofocus class="field text-center text-lg tracking-[0.5em] @error('code') field-error @enderror">@error('code')<span class="error-text">{{ $message }}</span>@enderror</label>
        <button type="submit" class="btn btn-primary btn-lg btn-block">{{ $button }}</button>
        <x-countdown :seconds="$expiresIn" label="Code expires in">
            <x-slot:expired>This code has expired. Tap “Resend code” for a new one.</x-slot:expired>
        </x-countdown>
    </form>
    <form method="post" action="{{ route('otp.resend') }}" class="mt-5 flex items-center justify-between text-sm" x-data="{ wait: {{ (int) $resendIn }} }" x-init="if (wait > 0) { const t = setInterval(() => { if (--wait <= 0) clearInterval(t) }, 1000) }">
        @csrf
        <button type="submit" class="font-bold text-red hover:underline disabled:font-normal disabled:text-muted disabled:no-underline" :disabled="wait > 0"><span x-text="wait > 0 ? 'Resend code in ' + wait + 's' : 'Resend code'">Resend code</span></button>
        <a href="{{ $backUrl }}" class="font-semibold text-muted hover:underline">Change number</a>
    </form>
</x-auth-shell>
@endsection
