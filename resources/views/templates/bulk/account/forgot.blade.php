@extends('layouts.app')

{{-- Forgot password, in the store's own layout. Same field as the shared form: a WhatsApp code goes to the phone. --}}

@section('content')
<x-auth-card heading="Reset your password" intro="Enter the phone number on your account and we'll send a code on WhatsApp to choose a new password.">
    <form method="post" action="{{ route('password.send-code') }}" class="space-y-4" novalidate>
        @csrf
        <label class="bk-label">Phone number<span class="bk-req">*</span>
            <input name="phone" type="tel" inputmode="tel" autocomplete="tel" autofocus class="bk-input" value="{{ old('phone') }}">
            @error('phone')<span class="bk-error">{{ $message }}</span>@enderror
        </label>
        <button type="submit" class="bk-btn bk-btn-primary bk-btn-block !py-3 text-base">Send code</button>
    </form>
    <p class="bk-muted mt-6 text-center text-sm"><a href="{{ route('login') }}" class="bk-link">← Back to sign in</a></p>
</x-auth-card>
@endsection
