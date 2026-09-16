@extends('layouts.app')

@section('content')
@php $label = 'block text-[0.6875rem] uppercase tracking-[0.2em] text-smoke'; @endphp
<x-account-shell title="Account information">
    <div class="grid gap-10 lg:grid-cols-2">
        <form method="post" action="{{ route('account.profile.update') }}" class="space-y-5 border border-ink/10 p-6 lg:p-8">
            @csrf @method('PUT')
            <h2 class="font-serif text-2xl">Your details</h2>
            <label class="block"><span class="{{ $label }}">Full name</span><input name="name" required value="{{ old('name', $user->name) }}" class="input-luxe">@error('name')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="{{ $label }}">Email</span><input name="email" type="email" required value="{{ old('email', $user->email) }}" class="input-luxe">@error('email')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="{{ $label }}">Phone</span><input name="phone" type="tel" value="{{ old('phone', $user->phone) }}" class="input-luxe"></label>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </form>

        <form method="post" action="{{ route('account.password.update') }}" class="space-y-5 border border-ink/10 p-6 lg:p-8">
            @csrf @method('PUT')
            <h2 class="font-serif text-2xl">Change password</h2>
            <label class="block"><span class="{{ $label }}">Current password</span><input name="current_password" type="password" required class="input-luxe" autocomplete="current-password">@error('current_password')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="{{ $label }}">New password</span><input name="password" type="password" required class="input-luxe" autocomplete="new-password">@error('password')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="{{ $label }}">Confirm new password</span><input name="password_confirmation" type="password" required class="input-luxe" autocomplete="new-password"></label>
            <button type="submit" class="btn btn-outline">Update password</button>
        </form>
    </div>

    <div class="mt-10 border border-ink/10 p-6 lg:p-8">
        <h2 class="font-serif text-2xl">Sign out</h2>
        <p class="mt-2 text-sm text-smoke">Sign out of your account on this device.</p>
        <form method="post" action="{{ route('logout') }}" class="mt-4">@csrf<button type="submit" class="btn btn-outline btn-sm">Sign out</button></form>
    </div>
</x-account-shell>
@endsection
