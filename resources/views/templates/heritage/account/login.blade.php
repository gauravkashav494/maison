@extends('layouts.app')

@section('content')
<x-auth-shell title="Welcome back" text="Log in to see your orders, addresses and saved items.">
    <form method="post" action="{{ route('login') }}" class="space-y-4" novalidate>
        @csrf
        <label class="block"><span class="label">Phone number<span class="req" aria-hidden="true">*</span></span><input name="phone" type="tel" inputmode="tel" autofocus value="{{ old('phone') }}" class="field @error('phone') field-error @enderror" autocomplete="tel">@error('phone')<span class="error-text">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="label">Password<span class="req" aria-hidden="true">*</span></span><input name="password" type="password" class="field" autocomplete="current-password">@error('password')<span class="error-text">{{ $message }}</span>@enderror</label>
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" class="check"> Keep me logged in</label>
            <a href="{{ route('password.request') }}" class="font-semibold text-red hover:underline">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Log in</button>
    </form>
    <a href="{{ route('login.otp') }}" class="btn btn-outline btn-lg btn-block mt-3">Log in with WhatsApp code</a>
    <p class="mt-5 text-center text-sm text-muted">New here? <a href="{{ route('register') }}" class="font-bold text-red hover:underline">Create an account</a></p>
</x-auth-shell>
@endsection
