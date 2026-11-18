@extends('layouts.app')

@section('content')
<x-auth-shell auth-quote="No password needed. We send a one-time code to your WhatsApp.">
    <p class="eyebrow text-taupe">Account</p>
    <h1 class="display-sm mt-3">Sign in with WhatsApp</h1>
    <p class="mt-4 text-sm text-smoke">Enter the phone number on your account and we will send a code on WhatsApp.</p>
    <form method="post" action="{{ route('login.otp') }}" class="mt-8 space-y-6" novalidate>
        @csrf
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Phone number<span class="req" aria-hidden="true">*</span></span><input name="phone" type="tel" inputmode="tel" autofocus value="{{ old('phone') }}" class="input-luxe" autocomplete="tel">@error('phone')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
        <button type="submit" class="btn btn-primary btn-lg w-full">Send code</button>
    </form>
    <p class="mt-8 text-center text-sm text-smoke"><a href="{{ route('login') }}" class="link-underline text-ink">Sign in with password instead</a></p>
</x-auth-shell>
@endsection
