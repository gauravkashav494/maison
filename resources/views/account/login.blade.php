@extends('layouts.app')

@section('content')
<x-auth-shell auth-quote="Welcome back. Your wishlist, orders and addresses are where you left them.">
    <p class="eyebrow text-taupe">Account</p>
    <h1 class="display-sm mt-3">Sign in</h1>
    <form method="post" action="{{ route('login') }}" class="mt-8 space-y-6">
        @csrf
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Email</span><input name="email" type="email" required autofocus value="{{ old('email') }}" class="input-luxe" autocomplete="email"></label>
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Password</span><input name="password" type="password" required class="input-luxe" autocomplete="current-password"></label>
        @error('email')<p class="text-sm text-rouge">{{ $message }}</p>@enderror
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" class="accent-ink"> Remember me</label>
            <a href="{{ route('password.request') }}" class="link-underline text-smoke">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-full">Sign in</button>
    </form>
    <p class="mt-8 text-center text-sm text-smoke">New to Maison Élan? <a href="{{ route('register') }}" class="link-underline text-ink">Create an account</a></p>
</x-auth-shell>
@endsection
