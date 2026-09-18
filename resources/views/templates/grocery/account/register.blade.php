@extends('layouts.app')

@section('content')
<x-auth-shell title="Create your account" text="Faster checkout, order tracking and member-only offers.">
    <form method="post" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <label class="block"><span class="label">Full name</span><input name="name" required value="{{ old('name') }}" class="field @error('name') field-error @enderror" autocomplete="name">@error('name')<span class="error-text">{{ $message }}</span>@enderror</label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block"><span class="label">Email</span><input name="email" type="email" required value="{{ old('email') }}" class="field @error('email') field-error @enderror" autocomplete="email">@error('email')<span class="error-text">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="label">Phone (optional)</span><input name="phone" type="tel" value="{{ old('phone') }}" class="field" autocomplete="tel"></label>
        </div>
        <label class="block"><span class="label">Password</span><input name="password" type="password" required class="field @error('password') field-error @enderror" autocomplete="new-password">@error('password')<span class="error-text">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="label">Confirm password</span><input name="password_confirmation" type="password" required class="field" autocomplete="new-password"></label>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Create account</button>
        <p class="text-center text-xs text-mist">By signing up you agree to our <a href="/terms" class="underline">Terms</a> and <a href="/privacy" class="underline">Privacy Policy</a>.</p>
    </form>
    <p class="mt-5 text-center text-sm text-slate">Already have an account? <a href="{{ route('login') }}" class="font-bold text-leaf hover:underline">Log in</a></p>
</x-auth-shell>
@endsection
