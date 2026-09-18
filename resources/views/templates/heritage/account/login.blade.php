@extends('layouts.app')

@section('content')
<x-auth-shell title="Welcome back" text="Log in to see your orders, addresses and saved items.">
    <form method="post" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <label class="block"><span class="label">Email</span><input name="email" type="email" required autofocus value="{{ old('email') }}" class="field @error('email') field-error @enderror" autocomplete="email">@error('email')<span class="error-text">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="label">Password</span><input name="password" type="password" required class="field" autocomplete="current-password"></label>
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" class="check"> Keep me logged in</label>
            <a href="{{ route('password.request') }}" class="font-semibold text-red hover:underline">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Log in</button>
    </form>
    <p class="mt-5 text-center text-sm text-muted">New here? <a href="{{ route('register') }}" class="font-bold text-red hover:underline">Create an account</a></p>
</x-auth-shell>
@endsection
