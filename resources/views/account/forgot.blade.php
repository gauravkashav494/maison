@extends('layouts.app')

@section('content')
<x-auth-shell auth-quote="We will send a secure link to reset your password.">
    <p class="eyebrow text-taupe">Account</p>
    <h1 class="display-sm mt-3">Forgot password</h1>
    <p class="mt-4 text-sm text-smoke">Enter the email you use for your account and we will send a reset link.</p>
    <form method="post" action="{{ route('password.email') }}" class="mt-8 space-y-6">
        @csrf
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Email</span><input name="email" type="email" required autofocus value="{{ old('email') }}" class="input-luxe">@error('email')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
        <button type="submit" class="btn btn-primary btn-lg w-full">Send reset link</button>
    </form>
    <p class="mt-8 text-center text-sm text-smoke"><a href="{{ route('login') }}" class="link-underline text-ink">Back to sign in</a></p>
</x-auth-shell>
@endsection
