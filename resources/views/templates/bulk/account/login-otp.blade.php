@extends('layouts.app')

{{-- Sign in with a WhatsApp code, in the store's own layout. --}}

@section('content')
<x-auth-card heading="Sign in with WhatsApp" intro="No password needed. We'll send a one-time code to the WhatsApp on your account.">
    <form method="post" action="{{ route('login.otp') }}" class="space-y-4" novalidate>
        @csrf
        <label class="bk-label">Phone number<span class="bk-req">*</span>
            <input name="phone" type="tel" inputmode="tel" autofocus autocomplete="tel" class="bk-input" value="{{ old('phone') }}">
            @error('phone')<span class="bk-error">{{ $message }}</span>@enderror
        </label>
        <button type="submit" class="bk-btn bk-btn-primary bk-btn-block !py-3 text-base">Send code</button>
    </form>
    <p class="bk-muted mt-6 text-center text-sm"><a href="{{ route('login') }}" class="bk-link">Sign in with password instead</a></p>
</x-auth-card>
@endsection
