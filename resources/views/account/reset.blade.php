@extends('layouts.app')

@section('content')
<x-auth-shell auth-quote="Choose a new password to secure your account.">
    <p class="eyebrow text-taupe">Account</p>
    <h1 class="display-sm mt-3">Reset password</h1>
    <form method="post" action="{{ route('password.update') }}" class="mt-8 space-y-6">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Email</span><input name="email" type="email" required value="{{ old('email', $email) }}" class="input-luxe">@error('email')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">New password</span><input name="password" type="password" required class="input-luxe" autocomplete="new-password">@error('password')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Confirm password</span><input name="password_confirmation" type="password" required class="input-luxe" autocomplete="new-password"></label>
        <button type="submit" class="btn btn-primary btn-lg w-full">Reset password</button>
    </form>
</x-auth-shell>
@endsection
