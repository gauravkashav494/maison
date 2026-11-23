@extends('layouts.app')

@section('content')
<x-auth-shell :auth-quote="\App\Support\CustomerOtp::enabled() ? 'We will send a one-time code to your WhatsApp to reset your password.' : 'We will email you a link to reset your password.'">
    <p class="eyebrow text-taupe">Account</p>
    <h1 class="display-sm mt-3">Forgot password</h1>
    <p class="mt-4 text-sm text-smoke">@otp Enter the phone number on your account and we will send a code on WhatsApp to reset your password. @else Enter the phone number on your account and we will email you a link to reset your password. @endotp</p>
    <form method="post" action="{{ route('password.send-code') }}" class="mt-8 space-y-6" novalidate>
        @csrf
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Phone number<span class="req" aria-hidden="true">*</span></span><input name="phone" type="tel" inputmode="tel" autocomplete="tel" autofocus value="{{ old('phone') }}" class="input-luxe">@error('phone')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
        <button type="submit" class="btn btn-primary btn-lg w-full">@otp Send code @else Email me a reset link @endotp</button>
    </form>
    <p class="mt-8 text-center text-sm text-smoke"><a href="{{ route('login') }}" class="link-underline text-ink">Back to sign in</a></p>
</x-auth-shell>
@endsection
