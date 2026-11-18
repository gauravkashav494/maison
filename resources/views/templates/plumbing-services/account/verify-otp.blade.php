@extends('layouts.app', ['appBar' => ['title' => 'Verify', 'back' => true]])

@section('content')
<x-auth-shell :title="$heading" :text="$intro">
    <form method="post" action="{{ route('otp.verify') }}" class="space-y-4" novalidate>
        @csrf
        <div><label class="label" for="code">WhatsApp code<span class="req" aria-hidden="true">*</span></label><input name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ $codeLength }}" autofocus class="field text-center text-lg tracking-[0.5em] @error('code') field-error @enderror" id="code">@error('code')<p class="error">{{ $message }}</p>@enderror</div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">{{ $button }}</button>
        <p class="text-center text-xs text-slate">The code is valid for {{ $validFor }} minutes.</p>
    </form>
    <form method="post" action="{{ route('otp.resend') }}" class="mt-5 flex items-center justify-between text-sm" x-data="{ wait: {{ (int) $resendIn }} }" x-init="if (wait > 0) { const t = setInterval(() => { if (--wait <= 0) clearInterval(t) }, 1000) }">
        @csrf
        <button type="submit" class="font-bold text-primary hover:underline disabled:font-normal disabled:text-slate disabled:no-underline" :disabled="wait > 0"><span x-text="wait > 0 ? 'Resend code in ' + wait + 's' : 'Resend code'">Resend code</span></button>
        <a href="{{ $backUrl }}" class="font-semibold text-slate hover:underline">Change number</a>
    </form>
</x-auth-shell>
@endsection
