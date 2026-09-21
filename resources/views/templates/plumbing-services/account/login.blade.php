@extends('layouts.app', ['appBar' => ['title' => 'Log in', 'back' => true]])

@section('content')
<x-auth-shell title="Welcome back" text="Log in to see your bookings and saved addresses.">
    <form method="post" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div><label class="label" for="email">Email</label><input id="email" name="email" type="email" required autofocus value="{{ old('email') }}" class="field @error('email') field-error @enderror" autocomplete="email">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="password">Password</label><input id="password" name="password" type="password" required class="field" autocomplete="current-password"></div>
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" class="check"> Keep me logged in</label>
            <a href="{{ route('password.request') }}" class="font-semibold text-primary hover:underline">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Log in</button>
    </form>
    <p class="mt-5 text-center text-sm text-slate">New here? <a href="{{ route('register') }}" class="font-bold text-primary hover:underline">Create an account</a></p>
    <p class="mt-2 text-center text-xs text-slate">No account needed to book — <a href="{{ route('booking.create') }}" class="font-semibold text-primary">book a plumber</a> or <a href="{{ route('bookings') }}" class="font-semibold text-primary">track a request</a>.</p>
</x-auth-shell>
@endsection