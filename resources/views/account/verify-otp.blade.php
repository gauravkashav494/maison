@extends('layouts.app')

@section('content')
<x-auth-shell auth-quote="One quick check on WhatsApp and you are in.">
    <p class="eyebrow text-taupe">Account</p>
    <h1 class="display-sm mt-3">{{ $heading }}</h1>
    <p class="mt-4 text-sm text-smoke">{{ $intro }}</p>
    <form method="post" action="{{ route('otp.verify') }}" class="mt-8 space-y-6" novalidate>
        @csrf
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">WhatsApp code<span class="req" aria-hidden="true">*</span></span><input name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ $codeLength }}" autofocus class="input-luxe text-center text-lg tracking-[0.5em]">@error('code')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
        <button type="submit" class="btn btn-primary btn-lg w-full">{{ $button }}</button>
        <p class="text-[0.6875rem] leading-relaxed text-taupe">The code is valid for {{ $validFor }} minutes.</p>
    </form>
    <form method="post" action="{{ route('otp.resend') }}" class="mt-6 flex items-center justify-between text-sm" x-data="{ wait: {{ (int) $resendIn }} }" x-init="if (wait > 0) { const t = setInterval(() => { if (--wait <= 0) clearInterval(t) }, 1000) }">
        @csrf
        <button type="submit" class="link-underline text-ink disabled:text-taupe disabled:no-underline" :disabled="wait > 0"><span x-text="wait > 0 ? 'Resend code in ' + wait + 's' : 'Resend code'">Resend code</span></button>
        <a href="{{ $backUrl }}" class="link-underline text-smoke">Change number</a>
    </form>
</x-auth-shell>
@endsection
