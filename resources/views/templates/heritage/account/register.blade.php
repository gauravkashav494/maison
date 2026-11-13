@extends('layouts.app')

@section('content')
<x-auth-shell title="Create your account" text="Faster checkout, order tracking and member-only offers.">
    <form method="post" action="{{ route('register') }}" class="space-y-4" novalidate>
        @csrf
        <label class="block"><span class="label">Full name<span class="req" aria-hidden="true">*</span></span><input name="name" value="{{ old('name') }}" class="field @error('name') field-error @enderror" autocomplete="name">@error('name')<span class="error-text">{{ $message }}</span>@enderror</label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block"><span class="label">Email<span class="req" aria-hidden="true">*</span></span><input name="email" type="email" value="{{ old('email') }}" class="field @error('email') field-error @enderror" autocomplete="email">@error('email')<span class="error-text">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="label">Phone (optional)</span><input name="phone" type="tel" value="{{ old('phone') }}" class="field" autocomplete="tel">@error('phone')<span class="error-text">{{ $message }}</span>@enderror</label>
        </div>
        <label class="block"><span class="label">Password<span class="req" aria-hidden="true">*</span></span><input name="password" type="password" class="field @error('password') field-error @enderror" autocomplete="new-password">@error('password')<span class="error-text">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="label">Confirm password<span class="req" aria-hidden="true">*</span></span><input name="password_confirmation" type="password" class="field" autocomplete="new-password"></label>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Create account</button>
        <p class="text-center text-xs text-muted">By signing up you agree to our <a href="/terms" class="underline">Terms</a> and <a href="/privacy" class="underline">Privacy Policy</a>.</p>
    </form>
    <p class="mt-5 text-center text-sm text-muted">Already have an account? <a href="{{ route('login') }}" class="font-bold text-red hover:underline">Log in</a></p>
</x-auth-shell>
@endsection
