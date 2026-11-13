@extends('layouts.app')

{{-- Sign in, in the store's own layout. Same fields as the shared form. --}}

@section('content')
<x-auth-card heading="Sign in" intro="Welcome back. Your orders and saved addresses are where you left them.">
    <form method="post" action="{{ route('login') }}" class="space-y-4" novalidate>
        @csrf
        <label class="bk-label">Email<span class="bk-req">*</span>
            <input name="email" type="email" autofocus autocomplete="email" class="bk-input" value="{{ old('email') }}">
            @error('email')<span class="bk-error">{{ $message }}</span>@enderror
        </label>
        <label class="bk-label">Password<span class="bk-req">*</span>
            <input name="password" type="password" autocomplete="current-password" class="bk-input">
            @error('password')<span class="bk-error">{{ $message }}</span>@enderror
        </label>
        <div class="flex items-center justify-between gap-3 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" class="accent-[var(--bk-brand)]"> Keep me signed in</label>
            <a href="{{ route('password.request') }}" class="bk-link">Forgot password?</a>
        </div>
        <button type="submit" class="bk-btn bk-btn-primary bk-btn-block !py-3 text-base">Sign in</button>
    </form>
    <p class="bk-muted mt-6 text-center text-sm">New here? <a href="{{ route('register') }}" class="bk-link">Create a trade account</a></p>
</x-auth-card>
@endsection
