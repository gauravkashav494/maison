@extends('layouts.app')

{{-- The WhatsApp code step (sign-up, checkout, sign in), in the store's own layout. --}}

@section('content')
<x-auth-card :heading="$heading" :intro="$intro">
    <form method="post" action="{{ route('otp.verify') }}" class="space-y-4" novalidate>
        @csrf
        <label class="bk-label">WhatsApp code<span class="bk-req">*</span>
            <input name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ $codeLength }}" autofocus class="bk-input text-center text-lg tracking-[0.5em]">
            @error('code')<span class="bk-error">{{ $message }}</span>@enderror
        </label>
        <button type="submit" class="bk-btn bk-btn-primary bk-btn-block !py-3 text-base">{{ $button }}</button>
        <p class="bk-muted text-center text-xs">The code is valid for {{ $validFor }} minutes.</p>
    </form>
    <form method="post" action="{{ route('otp.resend') }}" class="mt-6 flex items-center justify-between gap-3 text-sm" x-data="{ wait: {{ (int) $resendIn }} }" x-init="if (wait > 0) { const t = setInterval(() => { if (--wait <= 0) clearInterval(t) }, 1000) }">
        @csrf
        <button type="submit" class="bk-link disabled:opacity-50" :disabled="wait > 0"><span x-text="wait > 0 ? 'Resend code in ' + wait + 's' : 'Resend code'">Resend code</span></button>
        <a href="{{ $backUrl }}" class="bk-link">Change number</a>
    </form>
</x-auth-card>
@endsection
