@extends('layouts.app')

@section('content')
<x-auth-shell auth-quote="Track orders, save addresses and keep a wishlist across every device.">
    <p class="eyebrow text-taupe">Account</p>
    <h1 class="display-sm mt-3">Create an account</h1>
    <form method="post" action="{{ route('register') }}" class="mt-8 space-y-6">
        @csrf
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Full name</span><input name="name" required value="{{ old('name') }}" class="input-luxe" autocomplete="name">@error('name')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Email</span><input name="email" type="email" required value="{{ old('email') }}" class="input-luxe" autocomplete="email">@error('email')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Phone (optional)</span><input name="phone" type="tel" value="{{ old('phone') }}" class="input-luxe" autocomplete="tel"></label>
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Password</span><input name="password" type="password" required class="input-luxe" autocomplete="new-password">@error('password')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Confirm password</span><input name="password_confirmation" type="password" required class="input-luxe" autocomplete="new-password"></label>
        <button type="submit" class="btn btn-primary btn-lg w-full">Create account</button>
        <p class="text-[0.6875rem] leading-relaxed text-taupe">By creating an account you agree to our <a href="/terms" class="underline">Terms</a> and <a href="/privacy" class="underline">Privacy Policy</a>.</p>
    </form>
    <p class="mt-8 text-center text-sm text-smoke">Already have an account? <a href="{{ route('login') }}" class="link-underline text-ink">Sign in</a></p>
</x-auth-shell>
@endsection
