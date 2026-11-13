@extends('layouts.app')

{{-- Forgot password, in the store's own layout. Same field as the shared form. --}}

@section('content')
<x-auth-card heading="Reset your password" intro="Enter the email on your account and we'll send you a link to choose a new password.">
    <form method="post" action="{{ route('password.email') }}" class="space-y-4" novalidate>
        @csrf
        <label class="bk-label">Email<span class="bk-req">*</span>
            <input name="email" type="email" autofocus autocomplete="email" class="bk-input" value="{{ old('email') }}">
            @error('email')<span class="bk-error">{{ $message }}</span>@enderror
        </label>
        <button type="submit" class="bk-btn bk-btn-primary bk-btn-block !py-3 text-base">Send reset link</button>
    </form>
    <p class="bk-muted mt-6 text-center text-sm"><a href="{{ route('login') }}" class="bk-link">← Back to sign in</a></p>
</x-auth-card>
@endsection
