@extends('layouts.app')

@section('content')
<x-auth-shell auth-quote="Choose a new password to secure your account.">
    <p class="eyebrow text-taupe">Account</p>
    <h1 class="display-sm mt-3">Choose a new password</h1>
    <form method="post" action="{{ route('password.new') }}" class="mt-8 space-y-6" novalidate>
        @csrf
        <x-countdown :seconds="$secondsLeft">
            <x-slot:expired>Time is up. <a href="{{ route('password.request') }}">Verify your number again</a> to reset your password.</x-slot:expired>
        </x-countdown>
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">New password<span class="req" aria-hidden="true">*</span></span><input name="password" type="password" autofocus class="input-luxe" autocomplete="new-password"><x-password-checklist />@error('password')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Confirm password<span class="req" aria-hidden="true">*</span></span><input name="password_confirmation" type="password" class="input-luxe" autocomplete="new-password"><x-password-match /></label>
        <button type="submit" class="btn btn-primary btn-lg w-full">Save new password</button>
    </form>
</x-auth-shell>
@endsection
