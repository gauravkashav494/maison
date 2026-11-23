@extends('layouts.app')

@section('content')
<x-auth-shell title="Forgot your password?" :text="\App\Support\CustomerOtp::enabled() ? 'Enter the phone number on your account and we will send a code on WhatsApp.' : 'Enter the phone number on your account and we will email you a link to reset your password.'">
    <form method="post" action="{{ route('password.send-code') }}" class="space-y-4" novalidate>
        @csrf
        <label class="block"><span class="label">Phone number<span class="req" aria-hidden="true">*</span></span><input name="phone" type="tel" inputmode="tel" autocomplete="tel" autofocus value="{{ old('phone') }}" class="field @error('phone') field-error @enderror">@error('phone')<span class="error-text">{{ $message }}</span>@enderror</label>
        <button type="submit" class="btn btn-primary btn-lg btn-block">@otp Send code @else Email me a reset link @endotp</button>
    </form>
    <p class="mt-5 text-center text-sm text-slate"><a href="{{ route('login') }}" class="font-bold text-primary hover:underline">Back to login</a></p>
</x-auth-shell>
@endsection
