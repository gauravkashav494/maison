@extends('layouts.app')

{{-- Create an account, in the store's own layout. Same fields as the shared form. --}}

@section('content')
<x-auth-card heading="Create a trade account" intro="Takes a minute. Next time, checkout fills in your details and any past order can be repeated.">
    <form method="post" action="{{ route('register') }}" class="grid gap-4 sm:grid-cols-2" novalidate>
        @csrf
        <label class="bk-label sm:col-span-2">Your name or business<span class="bk-req">*</span>
            <input name="name" autocomplete="name" autofocus class="bk-input" value="{{ old('name') }}">
            @error('name')<span class="bk-error">{{ $message }}</span>@enderror
        </label>
        <label class="bk-label">Email<span class="bk-req">*</span>
            <input name="email" type="email" autocomplete="email" class="bk-input" value="{{ old('email') }}">
            @error('email')<span class="bk-error">{{ $message }}</span>@enderror
        </label>
        <x-phone-field />
        <label class="bk-label">Password<span class="bk-req">*</span>
            <input name="password" type="password" autocomplete="new-password" class="bk-input">
            @error('password')<span class="bk-error">{{ $message }}</span>@enderror
        </label>
        <label class="bk-label">Confirm password<span class="bk-req">*</span>
            <input name="password_confirmation" type="password" autocomplete="new-password" class="bk-input">
        </label>
        <button type="submit" class="bk-btn bk-btn-primary bk-btn-block !py-3 text-base sm:col-span-2">Create account</button>
    </form>
    <p class="bk-muted mt-6 text-center text-sm">Already have an account? <a href="{{ route('login') }}" class="bk-link">Sign in</a></p>
</x-auth-card>
@endsection
